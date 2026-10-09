<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\RoomInventory;
use App\Models\Venue;
use App\Services\Coordinator\VenueCoordinatorTools;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CoordinatorBookingToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_booking_tool_creates_a_reference_and_holds_the_requested_rooms(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR']);
        $room = $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 1000000, 'capacity' => 30, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $startDate = CarbonImmutable::now()->addDays(5);
        foreach ([0, 1] as $offset) {
            RoomInventory::create(['room_id' => $room->id, 'event_date' => $startDate->addDays($offset)->toDateString(), 'total_rooms' => 3, 'booked_rooms' => 0, 'price' => 1000000]);
        }

        $tools = new VenueCoordinatorTools($venue, $conversation, 'en');
        $input = [
            'room_slug' => 'classroom-studio', 'start_date' => $startDate->toDateString(), 'end_date' => $startDate->addDay()->toDateString(),
            'participants' => 20, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222', 'organization' => 'PT Maju', 'event_title' => 'Sales training',
        ];

        $quote = $tools->dispatch('check_availability', $input);
        $this->assertSame(2, $quote['ui']['quote']['days']);
        $this->assertSame(2000000.0, $quote['ui']['quote']['grand_total']);

        $result = $tools->dispatch('create_booking_request', [...$input, 'rooms' => 2]);

        $booking = Booking::firstOrFail();
        $this->assertSame($booking->reference, $result['ui']['booking']['booking_reference']);
        $this->assertSame(2, $booking->room_count);
        $this->assertSame(20, $booking->participants);
        $this->assertSame('PT Maju', $booking->organization);
        $this->assertSame('Sales training', $booking->event_title);
        $this->assertSame(4000000.0, (float) $booking->total_price);
        $this->assertSame([2, 2], RoomInventory::orderBy('event_date')->pluck('booked_rooms')->all());

        $tooMany = $tools->dispatch('create_booking_request', [...$input, 'rooms' => 2]);
        $this->assertNull($tooMany['ui']);
        $this->assertSame(1, Booking::count());
    }

    public function test_ai_tools_quote_programmes_with_the_discount_and_filter_by_layout(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'multiday_discount_percent' => 10, 'intensive_discount_percent' => 20]);
        $small = $venue->rooms()->create(['name' => 'Boardroom', 'slug' => 'boardroom', 'room_type' => 'boardroom', 'base_price' => 600000, 'capacity' => 12, 'seating_layouts' => [['type' => 'boardroom', 'capacity' => 12]], 'is_active' => true]);
        $big = $venue->rooms()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'room_type' => 'auditorium', 'base_price' => 1500000, 'floor_label' => 'Lt. 1', 'capacity' => 100, 'seating_layouts' => [['type' => 'theatre', 'capacity' => 100], ['type' => 'classroom', 'capacity' => 60]], 'catering_available' => true, 'catering_price' => 100000, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $startDate = CarbonImmutable::now()->addDays(5)->startOfDay();
        foreach ([$small, $big] as $room) {
            foreach (range(0, 6) as $offset) {
                RoomInventory::create(['room_id' => $room->id, 'event_date' => $startDate->addDays($offset)->toDateString(), 'total_rooms' => 2, 'booked_rooms' => 0, 'price' => $room->base_price]);
            }
        }

        $tools = new VenueCoordinatorTools($venue, $conversation, 'en');
        $week = ['start_date' => $startDate->toDateString(), 'end_date' => $startDate->addDays(4)->toDateString()];

        $quote = $tools->dispatch('check_availability', [...$week, 'room_slug' => 'grand-hall'])['ui']['quote'];
        $this->assertSame(5, $quote['days']);
        $this->assertSame(20, $quote['discount_percent']);
        $this->assertSame(7500000.0, $quote['subtotal']);
        $this->assertSame(6000000.0, $quote['grand_total']);

        $withCatering = $tools->dispatch('check_availability', [...$week, 'room_slug' => 'grand-hall', 'participants' => 50, 'catering' => true])['ui']['quote'];
        $this->assertSame(25000000.0, $withCatering['catering_total']);

        $search = $tools->dispatch('search_rooms', [...$week, 'participants' => 40])['ui']['rooms'];
        $this->assertSame(['grand-hall'], array_column($search, 'room_slug'));
        $this->assertSame(6000000.0, $search[0]['total_price']);
        $this->assertSame('Lt. 1', $search[0]['floor_label']);

        $byLayout = $tools->dispatch('search_rooms', [...$week, 'participants' => 10, 'seating_layout' => 'boardroom'])['ui']['rooms'];
        $this->assertSame(['boardroom'], array_column($byLayout, 'room_slug'));

        $tooBigForClassroom = $tools->dispatch('search_rooms', [...$week, 'participants' => 80, 'seating_layout' => 'classroom']);
        $this->assertNull($tooBigForClassroom['ui']);

        $detail = json_decode($tools->dispatch('get_room_detail', ['room_slug' => 'boardroom'])['text'], true);
        $this->assertSame(12, $detail['capacity']);
        $this->assertSame(20, $detail['program_discounts']['intensive_discount_percent']);
    }

    public function test_a_programme_longer_than_the_online_limit_is_sent_to_the_sales_team(): void
    {
        $venue = Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published']);
        $venue->rooms()->create(['name' => 'Classroom', 'slug' => 'classroom', 'base_price' => 1, 'capacity' => 10, 'is_active' => true]);
        $conversation = Conversation::create(['venue_id' => $venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $start = CarbonImmutable::now()->addDays(5);
        $result = (new VenueCoordinatorTools($venue, $conversation, 'en'))->dispatch('create_booking_request', [
            'room_slug' => 'classroom', 'start_date' => $start->toDateString(), 'end_date' => $start->addDays(40)->toDateString(),
            'participants' => 5, 'guest_name' => 'Ayu', 'guest_phone' => '+62 811 111 222',
        ]);

        $this->assertNull($result['ui']);
        $this->assertStringContainsString('request_human_handover', $result['text']);
        $this->assertSame(0, Booking::count());
    }
}
