<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueLobbyTest extends TestCase
{
    use RefreshDatabase;

    private function publishedVenue(array $attributes = []): Venue
    {
        return Venue::create(['name' => 'Demo', 'slug' => 'demo', 'public_status' => 'published', 'currency' => 'IDR', ...$attributes]);
    }

    public function test_opening_screen_links_to_the_only_published_venue(): void
    {
        $this->publishedVenue();
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee(config('app.name'))
            ->assertSee('Enter Demo')
            ->assertSee('href="'.route('venue.rooms', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.facilities', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('href="'.route('venue.team', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertDontSee('Draft');
    }

    public function test_opening_screen_lists_every_published_venue_when_there_are_several(): void
    {
        Venue::create(['name' => 'First Venue', 'slug' => 'first', 'public_status' => 'published']);
        Venue::create(['name' => 'Second Venue', 'slug' => 'second', 'public_status' => 'published']);
        Venue::create(['name' => 'Hidden Venue', 'slug' => 'hidden']);

        $this->get('/')->assertOk()->assertSee('First Venue')->assertSee('Second Venue')->assertDontSee('Hidden Venue');
    }

    public function test_opening_screen_explains_when_no_venue_is_published(): void
    {
        $this->get('/?lang=en')->assertOk()->assertSee('The virtual venue is being prepared');
    }

    public function test_chat_has_separate_open_and_close_controls_without_submitting_the_composer(): void
    {
        $this->publishedVenue();

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-chat-launcher', false)
            ->assertSee('data-chat-composer', false)
            ->assertSee('type="button" data-chat-close', false)
            ->assertSee('aria-controls="coordinator-chat-log"', false);
    }

    public function test_chat_exposes_consistent_status_draft_and_loading_copy_for_each_locale(): void
    {
        $this->publishedVenue();

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('data-label-status-sent="Message sent"', false)
            ->assertSee('data-label-status-waiting="Waiting for staff reply"', false)
            ->assertSee('data-label-status-replied="Staff has replied"', false)
            ->assertSee('data-label-draft-title="Unsaved message"', false)
            ->assertSee('data-label-draft-keep="Keep typing"', false)
            ->assertSee('data-label-draft-discard="Discard draft"', false)
            ->assertSee('data-thinking-indicator', false)
            ->assertSee('data-chat-status', false)
            ->assertSee('data-draft-dialog', false);

        $this->get('/demo?lang=ja')
            ->assertOk()
            ->assertSee('data-label-status-sent="メッセージを送信しました"', false)
            ->assertSee('data-label-draft-keep="入力を続ける"', false);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('data-label-status-sent="Pesan terkirim"', false)
            ->assertSee('data-label-draft-discard="Buang draft"', false);
    }

    public function test_every_page_renders_in_every_language(): void
    {
        $venue = $this->publishedVenue(['multiday_discount_percent' => 10, 'intensive_discount_percent' => 20]);
        $room = $venue->rooms()->create([
            'name' => 'Hall', 'slug' => 'hall', 'room_type' => 'auditorium', 'base_price' => 1000000, 'capacity' => 50, 'size_sqm' => 100, 'floor_label' => 'Lt. 1',
            'seating_layouts' => [['type' => 'theatre', 'capacity' => 50], ['type' => 'u_shape', 'capacity' => 20]],
            'catering_available' => true, 'catering_price' => 150000, 'amenities' => ['projector', 'unknown_thing'], 'is_active' => true,
        ]);
        $facility = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Projector', 'body' => 'HD.', 'is_active' => true]);

        $paths = ['/', '/demo', '/demo/rooms', '/demo/rooms/hall', '/demo/facilities', "/demo/facilities/{$facility->id}", '/demo/info', '/demo/team', '/demo/reservation'];

        foreach (['id', 'en', 'ja'] as $locale) {
            foreach ($paths as $path) {
                $this->get($path.'?lang='.$locale)->assertOk();
            }
        }

        $this->get('/demo/rooms/hall?lang=en')->assertSee('Unknown Thing');
        $this->assertNotNull($room);
    }

    public function test_the_facilities_page_lists_only_active_facilities_of_the_current_venue(): void
    {
        $venue = $this->publishedVenue();
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Projector', 'body' => 'HD projector in every room.', 'is_active' => true, 'image_url' => 'https://example.test/projector.jpg']);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden studio', 'body' => 'Private.', 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Other projector', 'body' => 'Other venue.', 'is_active' => true]);

        $this->get('/demo/facilities')
            ->assertOk()
            ->assertSee('Projector')
            ->assertDontSee('Hidden studio')
            ->assertDontSee('Other projector')
            ->assertSee('class="facility-card"', false)
            ->assertSee('https://example.test/projector.jpg', false)
            ->assertSee('class="sheet stage-panel-right', false);

        $this->get('/demo?lang=en')->assertOk()->assertSee('Welcome to your')->assertSee('virtual venue');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('バーチャル会場');
    }

    public function test_unpublished_lobby_is_not_accessible(): void
    {
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);
        $this->get('/draft')->assertNotFound();
    }

    public function test_the_rooms_page_lists_only_active_rooms_of_the_current_venue(): void
    {
        $venue = $this->publishedVenue();
        $first = $venue->rooms()->create([
            'name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 950000, 'capacity' => 30, 'amenities' => ['projector'], 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Tables', 'sort_order' => 0]);
        $venue->rooms()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'capacity' => 120, 'is_active' => true, 'sort_order' => 1]);
        $venue->rooms()->create(['name' => 'Retired Room', 'slug' => 'retired-room', 'base_price' => 1, 'capacity' => 1, 'is_active' => false]);
        $other = Venue::create(['name' => 'Other', 'slug' => 'other', 'public_status' => 'published']);
        $other->rooms()->create(['name' => 'Foreign Room', 'slug' => 'foreign-room', 'base_price' => 1, 'capacity' => 1, 'is_active' => true]);

        $this->get('/demo/rooms?lang=en')
            ->assertOk()
            ->assertSee('Classroom Studio')
            ->assertSee('Grand Hall')
            ->assertSee('narrator-on-stage', false)
            ->assertSee('class="room-nav-thumb"', false)
            ->assertSee('href="'.route('venue.room', ['venueSlug' => 'demo', 'roomSlug' => 'classroom-studio', 'lang' => 'en']).'"', false)
            ->assertDontSee('Retired Room')
            ->assertDontSee('Foreign Room');
    }

    public function test_a_room_page_shows_its_details_layouts_gallery_and_neighbouring_rooms(): void
    {
        $venue = $this->publishedVenue(['multiday_discount_percent' => 10, 'intensive_discount_percent' => 20]);
        $first = $venue->rooms()->create([
            'name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'room_type' => 'classroom', 'base_price' => 1000000, 'size_sqm' => 60, 'capacity' => 36, 'floor_label' => 'Lt. 2',
            'seating_layouts' => [['type' => 'classroom', 'capacity' => 24], ['type' => 'u_shape', 'capacity' => 18]],
            'catering_available' => true, 'catering_price' => 185000, 'amenities' => ['projector', 'whiteboard'], 'is_active' => true, 'sort_order' => 0,
        ]);
        $first->images()->create(['image_url' => 'https://example.test/one.jpg', 'alt_text' => 'Tables', 'sort_order' => 0]);
        $first->images()->create(['image_url' => 'https://example.test/two.jpg', 'alt_text' => 'Screen', 'sort_order' => 1]);
        $venue->rooms()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 9500000, 'capacity' => 120, 'is_active' => true, 'sort_order' => 1]);

        $hallUrl = route('venue.room', ['venueSlug' => 'demo', 'roomSlug' => 'grand-hall', 'lang' => 'en']);

        $this->get('/demo/rooms/classroom-studio?lang=en')
            ->assertOk()
            ->assertSee('Room 01 / 02')
            ->assertSee('Tables')
            ->assertSee('36 participants')
            ->assertSee('Lt. 2')
            ->assertSee('HD projector')
            ->assertSee('Whiteboards')
            ->assertSee('Classroom')
            ->assertSee('U-shape')
            ->assertSee('class="layout-bar-fill" style="width: 67%"', false)
            ->assertSee("A 5-day programme from about IDR\u{00A0}4.000.000")
            ->assertSee('https://example.test/two.jpg', false)
            ->assertSee('Final availability and rates are confirmed by the venue team.')
            ->assertSee('rel="prev"', false)
            ->assertSee($hallUrl, false);

        $this->get('/demo/rooms/retired-or-unknown')->assertNotFound();
        $this->get('/demo/rooms/classroom-studio?lang=id')->assertOk()->assertSee('Tipe ruangan')->assertSee('Papan tulis');
    }

    public function test_room_pages_follow_the_venue_publication_and_room_state(): void
    {
        $venue = $this->publishedVenue();
        $venue->rooms()->create(['name' => 'Hidden', 'slug' => 'hidden', 'base_price' => 1, 'capacity' => 1, 'is_active' => false]);
        Venue::create(['name' => 'Draft', 'slug' => 'draft']);

        $this->get('/demo/rooms?lang=en')->assertOk()->assertSee('Room information will be available soon.');
        $this->get('/demo/rooms/hidden')->assertNotFound();
        $this->get('/draft/rooms')->assertNotFound();
    }

    public function test_the_lobby_moves_the_guest_on_to_the_rooms_session(): void
    {
        $venue = $this->publishedVenue();
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 950000, 'capacity' => 30, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('href="'.route('venue.rooms', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Menuju ruang pelatihan."', false)
            ->assertSee('href="'.route('venue.facilities', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('data-tour-line="Menuju fasilitas dan layanan."', false);

        $this->get('/demo/rooms?lang=id')
            ->assertOk()
            ->assertSee('data-tour-line="Kembali ke meja penyambutan."', false)
            ->assertSee('data-scene="rooms"', false)
            ->assertSee('data-session="02"', false);
    }

    public function test_reservation_wizard_and_team_channels_render_from_venue_data(): void
    {
        $venue = $this->publishedVenue(['whatsapp' => '+62 812-0000-1111', 'phone' => '+62 361 000', 'email' => 'booking@demo.test']);
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 950000, 'capacity' => 30, 'seating_layouts' => [['type' => 'classroom', 'capacity' => 20]], 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="On to the booking desk to plan your event."', false)
            ->assertSee('href="'.route('venue.team', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="On to the venue team."', false);

        $this->get('/demo/reservation?lang=en')
            ->assertOk()
            ->assertSee('data-scene="reservation"', false)
            ->assertSee('data-tour-line="Back to the welcome desk."', false)
            ->assertSee('data-step="5"', false)
            ->assertSee('value="classroom-studio"', false)
            ->assertSee('Step :current of :total', false)
            ->assertSee('name="seating_layout"', false)
            ->assertSee(route('reservation.store', 'demo'), false);

        $this->get('/demo/reservation?lang=id')->assertOk()->assertSee('Langkah :current dari :total', false);

        $this->get('/demo/reservation?lang=en&room=classroom-studio')
            ->assertOk()
            ->assertSee('data-preselect-room="classroom-studio"', false);

        $this->get('/demo/rooms/classroom-studio?lang=en')
            ->assertOk()
            ->assertSee('href="'.e(route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'en', 'room' => 'classroom-studio'])).'"', false);

        $this->get('/demo/team?lang=en')
            ->assertOk()
            ->assertSee('data-scene="team"', false)
            ->assertSee('data-tour-line="Back to the welcome desk."', false)
            ->assertSee('https://wa.me/6281200001111?text=', false)
            ->assertSee('tel:+62361000', false)
            ->assertSee('mailto:booking@demo.test', false);

        $this->get('/draft/team')->assertNotFound();
        $this->get('/draft/reservation')->assertNotFound();
    }

    public function test_every_session_shows_the_agenda_strip_with_its_own_step_lit(): void
    {
        $this->publishedVenue();

        foreach (['' => '01', '/rooms' => '02', '/facilities' => '03', '/info' => '04', '/reservation' => '05', '/team' => '06'] as $path => $session) {
            $page = $this->get('/demo'.$path.'?lang=en')
                ->assertOk()
                ->assertSee('class="agenda"', false)
                ->assertSee('data-session="'.$session.'"', false)
                ->assertSee('<span class="session-counter-code" data-session-code>'.$session.'</span>', false)
                ->getContent();

            $agenda = Str::between($page, 'class="agenda"', '</nav>');
            $this->assertSame(6, substr_count($agenda, 'class="agenda-num"'));
            $this->assertSame(1, substr_count($agenda, 'aria-current="page"'));
            $this->assertMatchesRegularExpression('/aria-current="page"\s*>\s*<span class="agenda-num" aria-hidden="true">'.$session.'<\/span>/', $agenda);
        }
    }

    public function test_venue_information_and_team_sessions_use_narrow_sheets(): void
    {
        $this->publishedVenue();

        $this->get('/demo/team?lang=en')->assertOk()->assertSee('class="sheet team-panel', false);
        $this->get('/demo/info?lang=en')->assertOk()->assertSee('class="sheet info-panel', false);
    }

    public function test_every_stage_scene_uses_the_same_chat_controls_and_dock_contract(): void
    {
        $venue = $this->publishedVenue();
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 950000, 'capacity' => 30, 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Projector', 'body' => 'In every room.', 'is_active' => true]);

        foreach (['/demo', '/demo/info', '/demo/rooms', '/demo/facilities', '/demo/reservation', '/demo/team'] as $path) {
            $this->get($path.'?lang=en')
                ->assertOk()
                ->assertSee('data-chat-dock="', false)
                ->assertSee('data-chat-launcher', false)
                ->assertSee('data-chat-composer', false)
                ->assertSee('aria-controls="coordinator-chat-log"', false);
        }
    }

    public function test_each_facility_has_its_own_page_with_neighbours_and_an_index(): void
    {
        $venue = $this->publishedVenue();
        $projector = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Projector', 'body' => 'HD, every room.', 'is_active' => true, 'sort_order' => 0]);
        $sound = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Sound system', 'body' => 'Wireless microphones.', 'is_active' => true, 'sort_order' => 1]);
        $hidden = $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden studio', 'body' => 'Private.', 'is_active' => false]);

        $url = fn ($item) => route('venue.facility', ['venueSlug' => 'demo', 'facilityId' => $item->id, 'lang' => 'en']);

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('data-scene="facilities"', false)
            ->assertSee($url($projector), false)
            ->assertSee($url($sound), false)
            ->assertDontSee($url($hidden), false);

        $this->get($url($projector))
            ->assertOk()
            ->assertSee('Facility 1 / 2')
            ->assertSee('HD, every room.')
            ->assertSee('Ask about this facility')
            ->assertSee('rel="prev"', false)
            ->assertSee($url($sound), false);

        $this->get($url($hidden))->assertNotFound();
        $this->get('/demo/facilities/999999')->assertNotFound();
        $this->get('/draft/facilities')->assertNotFound();
        $this->get('/draft/info')->assertNotFound();
    }

    public function test_the_venue_information_scene_lists_only_approved_about_policy_and_faq_entries(): void
    {
        $venue = $this->publishedVenue([
            'address' => 'Jl. Rasuna Said 8', 'city' => 'Jakarta', 'country' => 'Indonesia',
            'opening_time' => '08:30', 'closing_time' => '16:45', 'description' => 'A training venue.', 'latitude' => -6.2, 'longitude' => 106.83,
        ]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Cancellation', 'body' => 'Free until 7 days before the first day.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'faq', 'title' => 'Is parking free?', 'body' => 'Yes, ten spaces.', 'is_active' => true]);
        $venue->knowledgeItems()->create(['category' => 'policies', 'title' => 'Draft policy', 'body' => 'Not approved.', 'is_active' => false]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Projector', 'body' => 'Belongs to facilities.', 'is_active' => true]);

        $this->get('/demo?lang=en')
            ->assertOk()
            ->assertSee('href="'.route('venue.info', ['venueSlug' => 'demo', 'lang' => 'en']).'"', false)
            ->assertSee('data-tour-line="On to the venue information."', false)
            ->assertDontSee('Free until 7 days before the first day.');

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('data-scene="info"', false)
            ->assertSee('data-tour-line="Back to the welcome desk."', false)
            ->assertSee('Free until 7 days before the first day.')
            ->assertSee('Is parking free?')
            ->assertSee('class="info-hours-time"', false)
            ->assertSeeInOrder(['08:30', '16:45'])
            ->assertSee('https://www.google.com/maps?q=-6.2000000,106.8300000', false)
            ->assertDontSee('Not approved.');
    }

    public function test_the_lobby_shows_a_localised_loader_and_the_opening_screen_too(): void
    {
        $this->publishedVenue();

        $this->get('/demo?lang=id')->assertOk()->assertSee('data-stage-loader', false)->assertSee('Menyiapkan ruangan…');
        $this->get('/?lang=en')->assertOk()->assertSee('data-stage-exit', false)->assertSee('Setting up the room…');
    }

    public function test_the_coordinator_introduces_rooms_using_only_stored_venue_data(): void
    {
        $venue = $this->publishedVenue();
        $venue->rooms()->create([
            'name' => 'Workshop Lab', 'slug' => 'workshop-lab', 'description' => 'A hands-on room with a screen', 'room_type' => 'workshop', 'base_price' => 950000, 'size_sqm' => 70,
            'capacity' => 24, 'floor_label' => 'Lt. 3', 'seating_layouts' => [['type' => 'classroom', 'capacity' => 24], ['type' => 'cluster', 'capacity' => 20]], 'is_active' => true, 'sort_order' => 0,
        ]);
        $venue->rooms()->create(['name' => 'Boardroom', 'slug' => 'boardroom', 'room_type' => 'boardroom', 'base_price' => 650000, 'capacity' => 12, 'is_active' => true, 'sort_order' => 1]);

        $this->get('/demo/rooms?lang=en')
            ->assertOk()
            ->assertSee('data-narrator', false)
            ->assertSee('We have 2 training rooms, from IDR 650.000 per day.');

        $this->get('/demo/rooms/workshop-lab?lang=en')
            ->assertOk()
            ->assertSee('Workshop Lab. A hands-on room with a screen. It is a workshop lab of 70 m² for up to 24 participants. Set-ups: Classroom 24, Cluster 20. You will find it on Lt. 3. Rates start from IDR 950.000 per day');

        $this->get('/demo/rooms/boardroom?lang=en')
            ->assertOk()
            ->assertSee('Boardroom. It is a boardroom for up to 12 participants. Rates start from IDR 650.000 per day, and longer programmes get a lower daily rate. Final availability and rates are confirmed by our team.');

        $this->get('/demo/rooms?lang=id')->assertOk()->assertSee('Ada 2 ruangan, mulai dari IDR 650.000 per hari.');
    }

    public function test_no_rooms_narration_is_shown_when_the_venue_has_no_rooms(): void
    {
        $this->publishedVenue();

        $this->get('/demo/rooms?lang=en')->assertOk()->assertDontSee('data-key="rooms"', false)->assertDontSee('This is the rooms session');
    }

    public function test_the_coordinator_introduces_facilities_and_venue_information_from_stored_data(): void
    {
        $venue = $this->publishedVenue(['city' => 'Jakarta', 'country' => 'Indonesia']);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Projector', 'body' => "HD projector.\nHDMI and wireless.", 'is_active' => true, 'sort_order' => 0]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Sound system', 'body' => 'Wireless microphones.', 'is_active' => true, 'sort_order' => 1]);
        $venue->knowledgeItems()->create(['category' => 'facilities', 'title' => 'Hidden studio', 'body' => 'Private.', 'is_active' => false, 'sort_order' => 2]);

        $projector = $venue->knowledgeItems()->where('title', 'Projector')->firstOrFail();

        $this->get('/demo/facilities?lang=en')
            ->assertOk()
            ->assertSee('Every programme can count on 2 facilities and services, including Projector; Sound system.')
            ->assertDontSee('including Projector; Sound system; Hidden studio');

        $this->get(route('venue.facility', ['venueSlug' => 'demo', 'facilityId' => $projector->id, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('data-key="facility-', false)
            ->assertSee('data-text="HD projector.', false);

        $this->get('/demo/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Demo. The venue is in Jakarta, Indonesia. A training day runs from 08:00 to 17:00.');

        $this->get('/demo/info?lang=id')->assertOk()->assertSee('Selamat datang di Demo. Venue kami berada di Jakarta, Indonesia.');
    }

    public function test_venue_information_narration_skips_missing_data(): void
    {
        Venue::create(['name' => 'Bare', 'slug' => 'bare', 'public_status' => 'published']);

        $this->get('/bare/info?lang=en')
            ->assertOk()
            ->assertSee('Welcome to Bare. A training day runs from 08:00 to 17:00. Below you will find the address')
            ->assertDontSee('The venue is in');
    }

    public function test_the_sound_toggle_is_available_on_the_opening_screen_and_the_lobby_in_every_language(): void
    {
        $this->publishedVenue();

        $this->get('/?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('Sound on')->assertSee('Sound off');
        $this->get('/?lang=id')->assertOk()->assertSee('Suara aktif')->assertSee('Suara mati');
        $this->get('/demo?lang=en')->assertOk()->assertSee('data-sound-toggle', false)->assertSee('data-label-off="Sound off"', false);
        $this->get('/demo/info?lang=ja')
            ->assertOk()
            ->assertSee('サウンドオン')
            ->assertSee('data-lang="ja-JP"', false)
            ->assertSee('data-voice-preference="female"', false);
    }

    public function test_the_rooms_session_shows_a_directory_of_rooms(): void
    {
        $venue = $this->publishedVenue();
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 950000, 'capacity' => 30, 'is_active' => true, 'sort_order' => 0]);
        $venue->rooms()->create(['name' => 'Grand Hall', 'slug' => 'grand-hall', 'base_price' => 2200000, 'capacity' => 120, 'is_active' => true, 'sort_order' => 1]);
        $venue->rooms()->create(['name' => 'Retired Room', 'slug' => 'retired', 'base_price' => 1, 'capacity' => 1, 'is_active' => false]);

        $this->get('/demo/rooms/grand-hall?lang=id')
            ->assertOk()
            ->assertSee('class="room-directory"', false)
            ->assertSee('class="room-nav"', false)
            ->assertSee('mulai dari IDR&nbsp;950.000 / hari', false)
            ->assertDontSee('Retired Room')
            ->assertSee('href="'.route('venue.show', ['venueSlug' => 'demo', 'lang' => 'id']).'" class="agenda-item', false);

        // the open room is marked as current in the directory, the others are not
        $index = $this->get('/demo/rooms/grand-hall?lang=id')->getContent();
        $directory = Str::between($index, '<nav class="room-nav">', '</nav>');
        $this->assertSame(1, substr_count($directory, 'aria-current'));
        $this->assertStringContainsString('aria-current="page"', Str::after($directory, 'roomSlug=grand-hall') ?: $directory);

        $this->get('/demo?lang=id')->assertOk()->assertDontSee('class="room-nav"', false);
    }

    public function test_each_session_opens_onto_its_own_photograph_with_the_coordinator_posed_in_front(): void
    {
        $venue = $this->publishedVenue();
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 950000, 'capacity' => 30, 'is_active' => true]);

        $this->get('/demo')
            ->assertOk()
            ->assertSee('photo-1604328698692-f76ea9498e76', false)
            ->assertSee('class="stage-character" data-pose="standing"', false);
        $this->get('/demo/rooms')
            ->assertOk()
            ->assertSee('photo-1568992687947-868a62a9f521', false)
            ->assertSee('class="stage-character" data-pose="presenting"', false);
        $this->get('/demo/info')
            ->assertOk()
            ->assertSee('photo-1431540015161-0bf868a2d407', false)
            ->assertSee('class="stage-character" data-pose="standing"', false)
            ->assertSee('images/character/character avatar.jpg', false);
        $this->get('/demo/team')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);
        $this->get('/demo/reservation')->assertOk()->assertSee('class="stage-character" data-pose="consulting"', false);

        // the room page is too full to share with her, so only her avatar stays
        $this->get('/demo/rooms/classroom-studio')
            ->assertOk()
            ->assertSee('photo-1568992687947-868a62a9f521', false)
            ->assertSee('images/character/character avatar.jpg', false)
            ->assertDontSee('class="stage-character"', false);
    }

    public function test_facility_icons_follow_what_the_entry_is_about(): void
    {
        $venue = $this->publishedVenue();
        $icon = fn (string $title, array $tags = []) => $venue->knowledgeItems()->make(['category' => 'facilities', 'title' => $title, 'body' => '-', 'tags' => $tags])->iconName();

        $this->assertSame('projector', $icon('Proyektor dan layar'));
        $this->assertSame('sound', $icon('Sound system dan mikrofon'));
        $this->assertSame('whiteboard', $icon('Whiteboard dan flipchart'));
        $this->assertSame('wifi', $icon('Internet', ['wifi']));
        $this->assertSame('dining', $icon('Katering: makan siang dan coffee break'));
        $this->assertSame('stay', $icon('Penginapan peserta'));
        $this->assertSame('parking', $icon('Parkir bus'));
        $this->assertSame('transit', $icon('TransJakarta dan MRT'));
        $this->assertSame('transport', $icon('Antar-jemput bandara'));
        $this->assertSame('support', $icon('Teknisi di lokasi'));
        $this->assertSame('security', $icon('Kartu akses', ['access card']));
        $this->assertSame('place', $icon('Atraksi terdekat'));
        $this->assertSame('star', $icon('Kids club'));
    }

    public function test_every_agenda_step_leads_to_its_session_with_a_topic_for_the_coordinator(): void
    {
        $this->publishedVenue();

        $scenes = [
            'venue.rooms' => 'Saya ingin melihat pilihan ruang pelatihan yang tersedia.',
            'venue.facilities' => 'Perlengkapan dan layanan apa saja yang termasuk di ruangan?',
            'venue.info' => 'Apa jam sesi, aturan venue, dan kebijakan pembatalan?',
            'venue.reservation' => 'Saya ingin booking ruang pelatihan. Bantu saya cek ketersediaan.',
            'venue.team' => 'Saya ingin bicara dengan tim venue.',
        ];

        $response = $this->get('/demo?lang=id')->assertOk();

        foreach ($scenes as $route => $topic) {
            $url = route($route, ['venueSlug' => 'demo', 'lang' => 'id']);

            $response->assertSee('href="'.$url.'"', false)
                ->assertSeeInOrder(['class="agenda"', 'href="'.$url.'"', 'data-stage-exit', 'data-topic="'.$topic.'"'], false);
            $this->get($url)->assertOk();
        }
    }

    public function test_lobby_offers_the_rooms_and_a_booking_request_as_the_two_main_actions_in_the_guest_language(): void
    {
        $venue = $this->publishedVenue(['intensive_discount_percent' => 25]);
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 650000, 'capacity' => 30, 'is_active' => true]);

        $this->get('/demo?lang=id')
            ->assertOk()
            ->assertSee('class="btn btn-marker" data-stage-exit', false)
            ->assertSee('href="'.route('venue.rooms', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Lihat ruangan')
            ->assertSee('href="'.route('venue.reservation', ['venueSlug' => 'demo', 'lang' => 'id']).'"', false)
            ->assertSee('Rencanakan acara saya');

        $this->get('/demo?lang=en')->assertOk()->assertSee('See the rooms')->assertSee('Plan my event');
        $this->get('/demo?lang=ja')->assertOk()->assertSee('ルームを見る');
    }

    public function test_lobby_without_any_room_shows_no_actions_that_lead_nowhere(): void
    {
        $this->publishedVenue();

        $this->get('/demo?lang=en')->assertOk()->assertDontSee('lobby-cta', false)->assertDontSee('See the rooms');
    }

    public function test_lobby_stats_show_the_day_rate_from_the_programme_saving_and_the_session_hours(): void
    {
        $venue = $this->publishedVenue(['multiday_discount_percent' => 10, 'intensive_discount_percent' => 25, 'opening_time' => '08:30', 'closing_time' => '16:30']);
        $venue->rooms()->create(['name' => 'Classroom Studio', 'slug' => 'classroom-studio', 'base_price' => 650000, 'capacity' => 30, 'is_active' => true]);

        $this->get('/demo?lang=en')->assertOk()->assertSee('IDR 650.000')->assertSee('up to 25% off')->assertSee('08:30–16:30');
    }
}
