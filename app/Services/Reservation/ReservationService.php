<?php

namespace App\Services\Reservation;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;

/**
 * The single place where room prices, availability and booking requests are
 * computed. Both the AI coordinator tools and the guest-facing reservation
 * wizard go through here, so they can never disagree.
 */
class ReservationService
{
    /** A programme runs up to a month online; longer residencies go through the sales team. */
    public const MAX_DAYS = 30;

    /**
     * Whether the participants fit the room across the requested number of
     * rooms, in the given seating set-up (or the room's overall limit).
     */
    public function fitsCapacity(Room $room, int $participants, int $rooms, ?string $layout = null): bool
    {
        return $participants <= $room->capacityFor($layout) * $rooms;
    }

    /**
     * Prices a programme across every day in range (the first and the last
     * day both count), adds optional catering, then takes off the venue's
     * multi-day or intensive discount. Returns null if any day lacks enough
     * free rooms — this is the one place price and availability truth comes
     * from, never the model.
     *
     * @return array{days: int, daily: list<array{date: string, price: float}>, room_total: float, catering_total: float, subtotal: float, discount_percent: int, discount_total: float, grand_total: float, min_available_rooms: int}|null
     */
    public function quote(
        Room $room,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
        int $rooms = 1,
        bool $catering = false,
        int $participants = 0,
        bool $lockForUpdate = false,
    ): ?array {
        $days = (int) $startDate->diffInDays($endDate) + 1;

        if ($days < 1) {
            return null;
        }

        $query = RoomInventory::where('room_id', $room->id)
            ->whereDate('event_date', '>=', $startDate->toDateString())
            ->whereDate('event_date', '<=', $endDate->toDateString())
            ->orderBy('event_date');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $inventory = $query->get();

        if ($inventory->count() < $days) {
            return null;
        }

        $daily = [];
        $perRoomTotal = 0.0;
        $minAvailable = PHP_INT_MAX;

        foreach ($inventory as $day) {
            if ($day->availableRooms() < $rooms) {
                return null;
            }

            $daily[] = ['date' => $day->event_date->toDateString(), 'price' => (float) $day->price];
            $perRoomTotal += (float) $day->price;
            $minAvailable = min($minAvailable, $day->availableRooms());
        }

        $roomTotal = $perRoomTotal * $rooms;
        $cateringTotal = $catering && $room->catering_available && ! $room->catering_included
            ? (float) $room->catering_price * $participants * $days
            : 0.0;

        $subtotal = $roomTotal + $cateringTotal;
        $discountPercent = $room->venue->programDiscountPercent($days);
        $discountTotal = round($subtotal * $discountPercent / 100, 2);

        return [
            'days' => $days,
            'daily' => $daily,
            'room_total' => $roomTotal,
            'catering_total' => $cateringTotal,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_total' => $discountTotal,
            'grand_total' => $subtotal - $discountTotal,
            'min_available_rooms' => $minAvailable,
        ];
    }

    /**
     * Creates a pending booking request and holds the inventory for it.
     * Returns null when the room is no longer available for those days.
     *
     * @param  array{start_date: CarbonImmutable, end_date: CarbonImmutable, participants: int, rooms?: int, seating_layout?: ?string, catering?: bool, guest_name: string, guest_email?: ?string, guest_phone?: ?string, contact_type?: ?string, locale?: ?string, organization?: ?string, event_title?: ?string, notes?: ?string}  $data
     */
    public function createRequest(Venue $venue, Room $room, array $data, ?Conversation $conversation = null): ?Booking
    {
        $rooms = $data['rooms'] ?? 1;
        $catering = (bool) ($data['catering'] ?? false);

        $booking = DB::transaction(function () use ($venue, $room, $data, $conversation, $rooms, $catering) {
            $quote = $this->quote($room, $data['start_date'], $data['end_date'], $rooms, $catering, $data['participants'], lockForUpdate: true);

            if (! $quote) {
                return null;
            }

            $booking = Booking::create([
                'reference' => Booking::generateReference(),
                'venue_id' => $venue->id,
                'room_id' => $room->id,
                'conversation_id' => $conversation?->id,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'contact_type' => $data['contact_type'] ?? null,
                'locale' => $data['locale'] ?? null,
                'organization' => $data['organization'] ?? null,
                'event_title' => $data['event_title'] ?? null,
                'start_date' => $data['start_date']->toDateString(),
                'end_date' => $data['end_date']->toDateString(),
                'participants' => $data['participants'],
                'room_count' => $rooms,
                'seating_layout' => $room->offersLayout((string) ($data['seating_layout'] ?? '')) ? $data['seating_layout'] : null,
                'catering' => $catering && $room->catering_available,
                'total_price' => $quote['grand_total'],
                'status' => Booking::STATUS_PENDING,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->adjustInventory($booking, +1);

            return $booking;
        });

        if ($booking) {
            $venue->notifyStaff(new NewBookingRequest($booking));
            $this->notifyGuest($booking, new BookingRequestReceived($booking));
        }

        return $booking;
    }

    /**
     * Confirms or cancels a booking. Returns false when its current status
     * does not allow the change, so a cancelled booking can never come back
     * without its rooms being held again. Cancelling gives the rooms back.
     */
    public function changeStatus(Booking $booking, string $status): bool
    {
        $changed = DB::transaction(function () use ($booking, $status) {
            $current = Booking::whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if (! $current->canTransitionTo($status)) {
                return false;
            }

            if ($status === Booking::STATUS_CANCELLED) {
                $this->releaseInventory($current);
            }

            $current->update(['status' => $status]);

            return true;
        });

        $booking->refresh();

        if ($changed) {
            $this->notifyGuest($booking, new BookingStatusChanged($booking));
        }

        return $changed;
    }

    /**
     * Organisers who left an email address get a message; those who chose
     * WhatsApp or phone are answered by the team instead.
     */
    private function notifyGuest(Booking $booking, Notification $notification): void
    {
        if (filled($booking->guest_email)) {
            NotificationFacade::route('mail', $booking->guest_email)->notify($notification->locale($booking->locale));
        }
    }

    /**
     * Gives the rooms held by a booking back to the inventory.
     */
    public function releaseInventory(Booking $booking): void
    {
        $this->adjustInventory($booking, -1);
    }

    private function adjustInventory(Booking $booking, int $direction): void
    {
        RoomInventory::where('room_id', $booking->room_id)
            ->whereDate('event_date', '>=', $booking->start_date->toDateString())
            ->whereDate('event_date', '<=', $booking->end_date->toDateString())
            ->{$direction > 0 ? 'increment' : 'decrement'}('booked_rooms', $booking->room_count);
    }
}
