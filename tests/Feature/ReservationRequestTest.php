<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\User;
use App\Models\Venue;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReservationRequestTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;

    private Room $classroom;

    private Room $hall;

    private string $startDate;

    private string $endDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->venue = Venue::create([
            'name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', 'timezone' => 'Asia/Makassar',
            'default_locale' => 'en', 'whatsapp' => '+62 812-3456-7890', 'phone' => '+62 361 771234', 'email' => 'booking@demo.test',
        ]);

        $this->classroom = $this->venue->rooms()->create([
            'name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'room_type' => 'classroom', 'base_price' => 1000000, 'capacity' => 30,
            'seating_layouts' => [['type' => 'classroom', 'capacity' => 20], ['type' => 'theatre', 'capacity' => 30]],
            'catering_available' => true, 'catering_price' => 100000, 'is_active' => true, 'sort_order' => 0,
        ]);
        $this->hall = $this->venue->rooms()->create([
            'name' => 'Grand Hall', 'slug' => 'grand-hall', 'room_type' => 'auditorium', 'base_price' => 2000000, 'capacity' => 100,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        $this->startDate = $today->addDays(10)->toDateString();
        $this->endDate = $today->addDays(12)->toDateString();

        foreach ([$this->classroom->id => [2, 1000000], $this->hall->id => [1, 2000000]] as $roomId => [$rooms, $price]) {
            foreach (range(10, 12) as $offset) {
                RoomInventory::create([
                    'room_id' => $roomId, 'event_date' => $today->addDays($offset)->toDateString(),
                    'total_rooms' => $rooms, 'booked_rooms' => 0, 'price' => $price,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function program(array $overrides = []): array
    {
        return [
            'room_slug' => 'classroom-studio', 'start_date' => $this->startDate, 'end_date' => $this->endDate,
            'participants' => 20, 'rooms' => 1, 'locale' => 'en',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function request(array $overrides = []): array
    {
        return $this->program([
            'guest_name' => 'John Tan', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 1111',
            'organization' => 'PT Maju Jaya', 'event_title' => 'Leadership workshop', 'special_request' => 'Set up at 07:00', ...$overrides,
        ]);
    }

    /**
     * @return list<int>
     */
    private function bookedRooms(?Room $room = null): array
    {
        return RoomInventory::where('room_id', ($room ?? $this->classroom)->id)->orderBy('event_date')->pluck('booked_rooms')->all();
    }

    public function test_quote_counts_the_first_and_the_last_day_for_every_room(): void
    {
        $this->postJson('/demo/reservation/quote', $this->program(['rooms' => 2, 'participants' => 40]))
            ->assertOk()
            ->assertJson(['available' => true, 'days' => 3, 'room_total' => 6000000, 'catering_total' => 0, 'grand_total' => 6000000, 'currency' => 'IDR']);
    }

    public function test_a_single_day_programme_is_the_same_first_and_last_day(): void
    {
        $this->postJson('/demo/reservation/quote', $this->program(['end_date' => $this->startDate]))
            ->assertOk()
            ->assertJson(['days' => 1, 'grand_total' => 1000000]);
    }

    public function test_catering_is_priced_per_participant_per_day(): void
    {
        $this->postJson('/demo/reservation/quote', $this->program(['catering' => true, 'participants' => 10]))
            ->assertOk()
            ->assertJson(['catering_total' => 3000000, 'subtotal' => 6000000, 'grand_total' => 6000000]);

        // The hall offers no catering add-on, so the request for it adds nothing.
        $this->postJson('/demo/reservation/quote', $this->program(['room_slug' => 'grand-hall', 'catering' => true, 'participants' => 10]))
            ->assertOk()
            ->assertJson(['catering_total' => 0, 'grand_total' => 6000000]);
    }

    public function test_quote_rejects_invalid_dates_in_the_guest_language(): void
    {
        $yesterday = CarbonImmutable::now('Asia/Makassar')->subDay()->toDateString();

        $this->postJson('/demo/reservation/quote', $this->program(['start_date' => $yesterday, 'locale' => 'id']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.start_date.0', 'Hari pertama tidak boleh di masa lalu.');

        $this->postJson('/demo/reservation/quote', $this->program(['end_date' => CarbonImmutable::parse($this->startDate)->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.end_date.0', 'The last day cannot be before the first day.');
    }

    public function test_quote_rejects_programmes_longer_than_the_limit(): void
    {
        $tooLong = CarbonImmutable::parse($this->startDate)->addDays(ReservationService::MAX_DAYS)->toDateString();

        $this->postJson('/demo/reservation/quote', $this->program(['end_date' => $tooLong]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('end_date');
    }

    public function test_quote_rejects_a_group_larger_than_the_rooms_can_hold(): void
    {
        $this->postJson('/demo/reservation/quote', $this->program(['participants' => 31]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.participants.0', 'This room does not fit that many participants. Choose a larger room or add another room.');

        $this->postJson('/demo/reservation/quote', $this->program(['participants' => 31, 'rooms' => 2]))->assertOk();
    }

    public function test_the_chosen_seating_layout_sets_how_many_fit_in_a_room(): void
    {
        $this->postJson('/demo/reservation/quote', $this->program(['participants' => 25, 'seating_layout' => 'classroom']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');

        $this->postJson('/demo/reservation/quote', $this->program(['participants' => 25, 'seating_layout' => 'theatre']))->assertOk();

        // A layout the room does not offer is ignored, so the room's overall limit applies.
        $this->postJson('/demo/reservation/quote', $this->program(['participants' => 25, 'seating_layout' => 'banquet']))->assertOk();
    }

    public function test_quote_rejects_unknown_and_inactive_rooms(): void
    {
        $this->classroom->update(['is_active' => false]);

        $this->postJson('/demo/reservation/quote', $this->program())->assertUnprocessable()->assertJsonValidationErrors('room_slug');
        $this->postJson('/demo/reservation/quote', $this->program(['room_slug' => 'nope']))->assertUnprocessable()->assertJsonValidationErrors('room_slug');
    }

    public function test_an_unavailable_room_offers_alternatives_that_fit_the_group(): void
    {
        RoomInventory::where('room_id', $this->classroom->id)->update(['booked_rooms' => 2]);

        $this->postJson('/demo/reservation/quote', $this->program())
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_slug.0', 'This room is not available for those dates.')
            ->assertJsonPath('alternatives.0.slug', 'grand-hall')
            ->assertJsonPath('alternatives.0.total', 6000000);
    }

    public function test_quote_refuses_more_rooms_than_are_free(): void
    {
        $this->postJson('/demo/reservation/quote', $this->program(['room_slug' => 'grand-hall', 'rooms' => 2, 'participants' => 50]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_slug.0', 'This room is not available for those dates.');
    }

    public function test_submitting_creates_a_pending_request_holds_inventory_and_returns_handover_links(): void
    {
        $conversation = Conversation::create(['venue_id' => $this->venue->id, 'guest_token' => (string) Str::uuid(), 'locale' => 'en']);

        $response = $this->postJson('/demo/reservation', $this->request(['rooms' => 2, 'participants' => 35, 'seating_layout' => 'theatre', 'catering' => true, 'guest_token' => $conversation->guest_token]))
            ->assertCreated()
            ->assertJsonPath('status', Booking::STATUS_PENDING)
            ->assertJsonPath('total', 6000000 + 35 * 100000 * 3)
            ->assertJsonPath('handover.phone_url', 'tel:+62361771234');

        $booking = Booking::firstOrFail();
        $this->assertMatchesRegularExpression('/^TV-[A-Z0-9]{6}$/', $booking->reference);
        $response->assertJsonPath('reference', $booking->reference);
        $this->assertSame(2, $booking->room_count);
        $this->assertSame(35, $booking->participants);
        $this->assertSame('theatre', $booking->seating_layout);
        $this->assertTrue($booking->catering);
        $this->assertSame('PT Maju Jaya', $booking->organization);
        $this->assertSame('Leadership workshop', $booking->event_title);
        $this->assertSame('whatsapp', $booking->contact_type);
        $this->assertSame('+62 812 0000 1111', $booking->guest_phone);
        $this->assertNull($booking->guest_email);
        $this->assertSame('Set up at 07:00', $booking->notes);
        $this->assertSame($conversation->id, $booking->conversation_id);
        $this->assertSame(3, $booking->days());
        $this->assertSame([2, 2, 2], $this->bookedRooms());

        $whatsapp = $response->json('handover.whatsapp_url');
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $whatsapp);
        $message = urldecode(substr($whatsapp, strlen('https://wa.me/6281234567890?text=')));
        $this->assertStringContainsString('Name: John Tan', $message);
        $this->assertStringContainsString('First day: ', $message);
        $this->assertStringContainsString('Rooms: 2', $message);
        $this->assertStringContainsString('Room: Classroom Studio', $message);
        $this->assertStringContainsString('Programme: Leadership workshop', $message);
        $this->assertStringContainsString("Reference: {$booking->reference}", $message);
        $this->assertStringStartsWith('mailto:booking@demo.test?subject=', $response->json('handover.email_url'));
    }

    public function test_email_contacts_are_stored_as_email_and_validated(): void
    {
        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.contact_value.0', 'Please enter a valid email address.');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'phone', 'contact_value' => 'call me']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_value');

        $this->postJson('/demo/reservation', $this->request(['contact_type' => 'email', 'contact_value' => 'john@example.com']))->assertCreated();

        $booking = Booking::firstOrFail();
        $this->assertSame('john@example.com', $booking->guest_email);
        $this->assertNull($booking->guest_phone);
    }

    public function test_the_last_free_room_can_only_be_requested_once(): void
    {
        $this->postJson('/demo/reservation', $this->request(['room_slug' => 'grand-hall']))->assertCreated();

        $this->postJson('/demo/reservation', $this->request(['room_slug' => 'grand-hall', 'guest_name' => 'Late Guest']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.room_slug.0', 'This room is not available for those dates.');

        $this->assertSame(1, Booking::count());
    }

    public function test_reference_is_unique_per_request(): void
    {
        $this->postJson('/demo/reservation', $this->request())->assertCreated();
        $this->postJson('/demo/reservation', $this->request())->assertCreated();

        $this->assertSame(2, Booking::distinct()->count('reference'));
    }

    public function test_unpublished_venues_reject_reservations(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->postJson('/draft/reservation', $this->request())->assertNotFound();
        $this->postJson('/draft/reservation/quote', $this->program())->assertNotFound();
    }

    public function test_reservation_requests_are_rate_limited(): void
    {
        foreach (range(1, 20) as $attempt) {
            $this->postJson('/demo/reservation/quote', $this->program())->assertOk();
        }

        $this->postJson('/demo/reservation/quote', $this->program())->assertTooManyRequests();
    }

    public function test_cancelling_a_multi_room_booking_releases_every_held_room(): void
    {
        $this->postJson('/demo/reservation', $this->request(['rooms' => 2, 'participants' => 35]))->assertCreated();
        $booking = Booking::firstOrFail();

        $user = User::factory()->create();
        $this->venue->users()->attach($user->id, ['role' => 'owner', 'status' => 'active']);

        $this->actingAs($user)
            ->patch(route('admin.bookings.status', $booking), ['status' => Booking::STATUS_CANCELLED])
            ->assertRedirect();

        $this->assertSame([0, 0, 0], $this->bookedRooms());
        $this->assertSame(Booking::STATUS_CANCELLED, $booking->fresh()->status);
    }

    public function test_longer_programmes_are_quoted_with_the_multiday_or_intensive_discount(): void
    {
        $this->venue->update(['multiday_discount_percent' => 10, 'intensive_discount_percent' => 20]);

        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        foreach (range(20, 29) as $offset) {
            RoomInventory::create([
                'room_id' => $this->classroom->id, 'event_date' => $today->addDays($offset)->toDateString(),
                'total_rooms' => 2, 'booked_rooms' => 0, 'price' => 1000000,
            ]);
        }

        $programme = fn (int $days) => $this->program([
            'start_date' => $today->addDays(20)->toDateString(),
            'end_date' => $today->addDays(20 + $days - 1)->toDateString(),
        ]);

        $this->postJson('/demo/reservation/quote', $programme(2))
            ->assertOk()
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 2000000);

        $this->postJson('/demo/reservation/quote', $programme(3))
            ->assertOk()
            ->assertJsonPath('subtotal', 3000000)
            ->assertJsonPath('discount_percent', 10)
            ->assertJsonPath('discount_total', 300000)
            ->assertJsonPath('grand_total', 2700000);

        $this->postJson('/demo/reservation/quote', $programme(5))
            ->assertOk()
            ->assertJsonPath('discount_percent', 20)
            ->assertJsonPath('grand_total', 4000000);

        $this->postJson('/demo/reservation', [...$programme(5), 'guest_name' => 'Bootcamp Lead', 'contact_type' => 'whatsapp', 'contact_value' => '+62 812 0000 2222'])
            ->assertCreated()
            ->assertJsonPath('total', 4000000);
    }

    public function test_programmes_are_not_discounted_when_the_venue_offers_no_programme_rate(): void
    {
        $today = CarbonImmutable::now('Asia/Makassar')->startOfDay();
        foreach (range(20, 27) as $offset) {
            RoomInventory::create([
                'room_id' => $this->classroom->id, 'event_date' => $today->addDays($offset)->toDateString(),
                'total_rooms' => 2, 'booked_rooms' => 0, 'price' => 1000000,
            ]);
        }

        $this->postJson('/demo/reservation/quote', $this->program([
            'start_date' => $today->addDays(20)->toDateString(),
            'end_date' => $today->addDays(27)->toDateString(),
        ]))
            ->assertOk()
            ->assertJsonPath('discount_percent', 0)
            ->assertJsonPath('grand_total', 8000000);
    }
}
