<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentVenue;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomInventory;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Where staff open dates for booking and set what a room costs per day. The
 * coordinator and the reservation wizard only ever read this table.
 */
class RoomInventoryController extends Controller
{
    use ResolvesCurrentVenue;

    /** The longest range one submit may open, so a typo cannot create years of rows. */
    private const MAX_RANGE_DAYS = 366;

    public function index(Request $request, Room $room): View
    {
        $venue = $this->currentVenue($request);
        abort_if($room->venue_id !== $venue->id, 404);

        $month = $this->month($request, $venue->timezone);

        $inventory = $room->inventory()
            ->whereDate('event_date', '>=', $month->toDateString())
            ->whereDate('event_date', '<=', $month->endOfMonth()->toDateString())
            ->get()
            ->keyBy(fn (RoomInventory $day) => $day->event_date->toDateString());

        return view('admin.rooms.inventory', [
            'venue' => $venue,
            'room' => $room,
            'month' => $month,
            'days' => CarbonPeriod::create($month, $month->endOfMonth()),
            'inventory' => $inventory,
            'today' => now($venue->timezone)->toDateString(),
        ]);
    }

    public function store(Request $request, Room $room): RedirectResponse
    {
        $venue = $this->currentVenue($request);
        abort_if($room->venue_id !== $venue->id, 404);

        $today = now($venue->timezone)->toDateString();

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'total_rooms' => ['required', 'integer', 'min:0', 'max:1000'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
        ]);

        if (CarbonImmutable::parse($data['from'])->diffInDays($data['to']) >= self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Open at most '.self::MAX_RANGE_DAYS.' days at a time.']);
        }

        $range = CarbonPeriod::create($data['from'], $data['to']);

        DB::transaction(function () use ($room, $data, $range) {
            $existing = $room->inventory()
                ->whereDate('event_date', '>=', $data['from'])
                ->whereDate('event_date', '<=', $data['to'])
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RoomInventory $day) => $day->event_date->toDateString());

            if ($existing->contains(fn (RoomInventory $day) => $day->booked_rooms > $data['total_rooms'])) {
                throw ValidationException::withMessages([
                    'total_rooms' => 'Some dates in that range already have more rooms booked than this. Cancel those bookings first, or choose a higher number.',
                ]);
            }

            foreach ($range as $date) {
                $day = $existing->get($date->toDateString());

                if ($day) {
                    $day->update([
                        'total_rooms' => $data['total_rooms'],
                        'price' => $data['price'] ?? $day->price,
                    ]);

                    continue;
                }

                $room->inventory()->create([
                    'event_date' => $date->toDateString(),
                    'total_rooms' => $data['total_rooms'],
                    'booked_rooms' => 0,
                    'price' => $data['price'] ?? $room->base_price,
                ]);
            }
        });

        return redirect()
            ->route('admin.rooms.inventory.index', [$room, 'month' => substr($data['from'], 0, 7)])
            ->with('status', "Availability for \"{$room->name}\" updated from {$data['from']} to {$data['to']}.");
    }

    private function month(Request $request, string $timezone): CarbonImmutable
    {
        $requested = $request->query('month');

        if (is_string($requested) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $requested)) {
            return CarbonImmutable::createFromFormat('Y-m-d', $requested.'-01', $timezone)->startOfDay();
        }

        return now($timezone)->toImmutable()->startOfMonth();
    }
}
