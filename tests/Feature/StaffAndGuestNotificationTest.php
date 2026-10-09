<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\User;
use App\Models\Venue;
use App\Notifications\BookingRequestReceived;
use App\Notifications\BookingStatusChanged;
use App\Notifications\NewBookingRequest;
use App\Notifications\NewHandoverRequest;
use App\Services\Coordinator\VenueCoordinatorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffAndGuestNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Room $room;

    private User $owner;

    private User $inactiveStaff;

    private CarbonImmutable $checkIn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'default_locale' => 'en']);
        $this->room = $this->venue->rooms()->create(['name' => 'Studio', 'slug' => 'studio', 'translations' => ['ja' => ['name' => 'スタジオ']], 'base_price' => 500000, 'capacity' => 4, 'is_active' => true]);

        $this->owner = User::factory()->create();
        $this->inactiveStaff = User::factory()->create();
        $this->venue->users()->attach($this->owner->id, ['role' => 'owner', 'status' => 'active']);
        $this->venue->users()->attach($this->inactiveStaff->id, ['role' => 'staff', 'status' => 'suspended']);

        $this->checkIn = CarbonImmutable::now($this->venue->timezone)->addDays(5)->startOfDay();
        foreach ([0, 1] as $offset) {
            RoomInventory::create(['room_id' => $this->room->id, 'event_date' => $this->checkIn->addDays($offset)->toDateString(), 'total_rooms' => 2, 'booked_rooms' => 0, 'price' => 500000]);
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reservation(array $overrides = []): array
    {
        return [
            'room_slug' => 'studio', 'start_date' => $this->checkIn->toDateString(), 'end_date' => $this->checkIn->addDays(1)->toDateString(),
            'participants' => 2, 'rooms' => 1, 'locale' => 'id', 'guest_name' => 'Ayu', 'contact_type' => 'email', 'contact_value' => 'ayu@example.test',
            ...$overrides,
        ];
    }

    public function test_a_booking_request_notifies_active_staff_and_acknowledges_a_guest_who_left_an_email(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation())->assertCreated();

        $booking = Booking::firstOrFail();
        Notification::assertSentTo($this->owner, NewBookingRequest::class, fn ($notification) => $notification->booking->is($booking));
        Notification::assertNotSentTo($this->inactiveStaff, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ayu@example.test');
        $this->assertSame('id', $booking->locale);
    }

    public function test_a_guest_who_chose_whatsapp_gets_no_email_but_staff_are_still_told(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111']))->assertCreated();

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertNothingSentTo(new AnonymousNotifiable);
        Notification::assertSentOnDemandTimes(BookingRequestReceived::class, 0);
    }

    public function test_a_request_that_finds_no_free_room_notifies_nobody(): void
    {
        Notification::fake();

        $this->postJson('/demo/reservation', $this->reservation(['rooms' => 3, 'participants' => 6]))->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_the_coordinator_booking_tool_notifies_staff_and_remembers_the_guest_language(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'ja']);

        (new VenueCoordinatorTools($this->venue, $conversation, 'ja'))->dispatch('create_booking_request', [
            'room_slug' => 'studio', 'start_date' => $this->checkIn->toDateString(), 'end_date' => $this->checkIn->addDays(1)->toDateString(),
            'participants' => 2, 'guest_name' => 'Aki', 'guest_phone' => '+81 90 0000 0000', 'guest_email' => 'aki@example.test',
        ]);

        Notification::assertSentTo($this->owner, NewBookingRequest::class);
        Notification::assertSentOnDemand(BookingRequestReceived::class, fn ($notification, $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'aki@example.test');
        $this->assertSame('ja', Booking::firstOrFail()->locale);
    }

    public function test_a_handover_notifies_active_staff_with_a_link_to_the_conversation(): void
    {
        Notification::fake();
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        (new VenueCoordinatorTools($this->venue, $conversation, 'en'))->dispatch('request_human_handover', ['reason' => HandoverRequest::REASON_COMPLAINT, 'summary' => 'Noisy neighbours']);

        $handover = HandoverRequest::firstOrFail();
        Notification::assertSentTo($this->owner, NewHandoverRequest::class, fn ($notification) => $notification->handover->is($handover));
        Notification::assertNotSentTo($this->inactiveStaff, NewHandoverRequest::class);

        $mail = (new NewHandoverRequest($handover))->toMail($this->owner);
        $this->assertStringContainsString('Noisy neighbours', implode(' ', $mail->introLines));
        $this->assertSame(route('admin.handovers.show', $handover), $mail->actionUrl);
    }

    public function test_the_guest_emails_are_written_in_the_language_the_guest_used(): void
    {
        $booking = Booking::create([
            'reference' => 'TV-TEST01', 'venue_id' => $this->venue->id, 'room_id' => $this->room->id, 'guest_name' => 'Aki',
            'guest_email' => 'aki@example.test', 'participants' => 2, 'room_count' => 1, 'start_date' => $this->checkIn, 'end_date' => $this->checkIn->addDays(1), 'total_price' => 1000000,
            'status' => Booking::STATUS_CONFIRMED, 'locale' => 'ja',
        ]);

        $confirmed = (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable);
        $this->assertSame('ご予約が確定しました TV-TEST01', $confirmed->subject);
        $this->assertContains('ルーム: 1 × スタジオ', $confirmed->introLines);
        $this->assertContains('初日: '.$this->checkIn->year.'年'.$this->checkIn->month.'月'.$this->checkIn->day.'日', $confirmed->introLines);

        $booking->update(['status' => Booking::STATUS_CANCELLED, 'locale' => 'id']);
        $this->assertSame('Permintaan booking TV-TEST01 dibatalkan', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);

        $booking->update(['locale' => null]);
        $this->assertSame('Your booking request TV-TEST01 was cancelled', (new BookingStatusChanged($booking))->toMail(new AnonymousNotifiable)->subject);
    }
}
