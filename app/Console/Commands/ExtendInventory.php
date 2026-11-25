<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomInventory;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:extend {--days=365 : How many days ahead rooms should be open for booking}')]
#[Description('Open further days for every room, repeating the units and prices of the week before; existing days and bookings are never changed')]
class ExtendInventory extends Command
{
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $added = 0;

        Room::query()->where('is_active', true)->with('venue')->each(function (Room $room) use ($days, &$added): void {
            $added += $this->extend($room, $days);
        });

        $this->info("Opened {$added} day(s).");

        return self::SUCCESS;
    }

    private function extend(Room $room, int $days): int
    {
        $last = RoomInventory::query()->where('room_id', $room->id)->orderByDesc('event_date')->first();

        if (! $last) {
            return 0;
        }

        $horizon = CarbonImmutable::now($room->venue->timezone)->startOfDay()->addDays($days);
        $week = RoomInventory::query()->where('room_id', $room->id)
            ->whereDate('event_date', '>', $last->event_date->copy()->subDays(7)->toDateString())
            ->get()
            ->keyBy(fn (RoomInventory $day) => $day->event_date->toDateString())
            ->map(fn (RoomInventory $day) => ['total_rooms' => $day->total_rooms, 'price' => (float) $day->price])
            ->all();

        $rows = [];
        for ($date = CarbonImmutable::parse($last->event_date)->addDay(); $date->lte($horizon); $date = $date->addDay()) {
            $template = $week[$date->subDays(7)->toDateString()] ?? ['total_rooms' => $last->total_rooms, 'price' => (float) $last->price];
            $week[$date->toDateString()] = $template;

            $rows[] = [
                'room_id' => $room->id,
                'event_date' => $date->startOfDay()->toDateTimeString(),
                'total_rooms' => $template['total_rooms'],
                'booked_rooms' => 0,
                'price' => $template['price'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $inserted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $inserted += RoomInventory::query()->insertOrIgnore($chunk);
        }

        return $inserted;
    }
}
