<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExtendInventoryCommandTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private CarbonImmutable $today;

    protected function setUp(): void
    {
        parent::setUp();

        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $this->room = $venue->rooms()->create(['name' => 'Studio', 'slug' => 'studio', 'base_price' => 500000, 'capacity' => 2, 'is_active' => true]);
        $this->today = CarbonImmutable::now($venue->timezone)->startOfDay();

        for ($day = 0; $day < 7; $day++) {
            RoomInventory::create([
                'room_id' => $this->room->id,
                'event_date' => $this->today->addDays($day),
                'total_rooms' => 2,
                'booked_rooms' => $day === 2 ? 2 : 0,
                'price' => $day === 5 ? 600000 : 500000,
            ]);
        }
    }

    private function day(CarbonImmutable $date): ?RoomInventory
    {
        return RoomInventory::query()->where('room_id', $this->room->id)->whereDate('event_date', $date->toDateString())->first();
    }

    public function test_it_repeats_last_weeks_units_and_prices_without_touching_bookings(): void
    {
        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();

        $this->assertNotNull($this->day($this->today->addDays(30)));
        $this->assertNull($this->day($this->today->addDays(31)));
        $this->assertSame('600000.00', $this->day($this->today->addDays(12))->price);
        $this->assertSame('500000.00', $this->day($this->today->addDays(13))->price);
        $this->assertSame(0, $this->day($this->today->addDays(9))->booked_rooms);
        $this->assertSame(2, $this->day($this->today->addDays(2))->booked_rooms);
    }

    public function test_running_it_twice_adds_nothing_the_second_time(): void
    {
        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();
        $count = RoomInventory::count();

        $this->artisan('inventory:extend', ['--days' => 30])->expectsOutputToContain('Opened 0 day(s)')->assertSuccessful();
        $this->assertSame($count, RoomInventory::count());
        $this->assertSame(31, $count);
    }

    public function test_inactive_spaces_are_left_alone(): void
    {
        $this->room->update(['is_active' => false]);

        $this->artisan('inventory:extend', ['--days' => 30])->assertSuccessful();

        $this->assertSame(7, RoomInventory::count());
    }

    public function test_it_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'inventory:extend'));

        $this->assertCount(1, $events);
        $this->assertSame('0 1 * * *', $events->first()->expression);
    }
}
