<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Services\Reservation\ReservationHandover;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class VenuePageController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** The coordinator's face for the chat and narrator avatars. */
    private const AVATAR_IMAGE = 'images/character/character avatar.jpg';

    /**
     * The coordinator's poses, transparent cut-outs shown in front of the scene photo.
     *
     * @var array<string, string>
     */
    private const CHARACTER_POSES = [
        'standing' => 'images/character/character standing.png',
        'presenting' => 'images/character/character presenting.png',
        'consulting' => 'images/character/character consulting.png',
    ];

    /**
     * Which pose she takes in each session.
     *
     * @var array<string, string>
     */
    private const SCENE_POSES = [
        'rooms' => 'presenting',
        'reservation' => 'consulting',
        'team' => 'consulting',
    ];

    /** Days used to turn a day rate into the indicative one-week programme price shown on room pages. */
    private const PROGRAM_DAYS = 5;

    /**
     * Training-room photography behind each scene, so every session of the
     * site opens onto the venue itself.
     *
     * @var array<string, array{image: string, focus: string, focusMobile: string}>
     */
    private const SCENE_PHOTOS = [
        'lobby' => ['image' => 'https://images.unsplash.com/photo-1604328698692-f76ea9498e76?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '35% 50%'],
        'rooms' => ['image' => 'https://images.unsplash.com/photo-1568992687947-868a62a9f521?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 45%', 'focusMobile' => '40% 50%'],
        'facilities' => ['image' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'info' => ['image' => 'https://images.unsplash.com/photo-1431540015161-0bf868a2d407?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 45%', 'focusMobile' => '50% 45%'],
        'team' => ['image' => 'https://images.unsplash.com/photo-1556761175-5973dc0f32e7?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '50% 50%'],
        'reservation' => ['image' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=2000&q=80', 'focus' => 'center 50%', 'focusMobile' => '30% 50%'],
    ];

    private const OPENING_PHOTO = 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?auto=format&fit=crop&w=2000&q=80';

    /**
     * Each section of the site is a session of the programme, reached from
     * the agenda strip. The code is what the strip and the session counter show.
     *
     * @var array<string, string>
     */
    public const SESSIONS = [
        'lobby' => '01',
        'rooms' => '02',
        'facilities' => '03',
        'info' => '04',
        'reservation' => '05',
        'team' => '06',
    ];

    public function __construct(private readonly ReservationHandover $handover) {}

    /**
     * What the AI coordinator says when the guest moves on to a session.
     * Every sentence is built from stored venue data, never generated.
     *
     * @var array<string, array<string, string>>
     */
    private const NARRATION = [
        'en' => [
            'rooms' => 'This is the rooms session. We have :count training rooms, from :price per day. Open any of them and I will walk you through the set-ups, or ask me anything.',
            'rooms_one' => 'This is the rooms session. We have one training room, from :price per day. Open it and I will walk you through the set-ups, or ask me anything.',
            'type_size' => 'It is a :type of :size m² for up to :capacity participants.',
            'type_only' => 'It is a :type for up to :capacity participants.',
            'layouts' => 'Set-ups: :layouts.',
            'floor' => 'You will find it on :floor.',
            'price' => 'Rates start from :price per day, and longer programmes get a lower daily rate. Final availability and rates are confirmed by our team.',
            'facilities' => 'Every programme can count on :count facilities and services, including :examples. Choose one to read the details, or ask me anything.',
            'facilities_one' => 'Every programme can count on :examples. Open it to read the details, or ask me anything.',
            'info_welcome' => 'Welcome to :venue.',
            'info_place' => 'The venue is in :location.',
            'info_hours' => 'A training day runs from :in to :out.',
            'info_more' => 'Below you will find the address, house rules and frequently asked questions — or just ask me.',
            'tour_rooms' => 'On to the training rooms.',
            'tour_facilities' => 'On to the facilities and services.',
            'tour_info' => 'On to the venue information.',
            'tour_team' => 'On to the venue team.',
            'tour_reservation' => 'On to the booking desk to plan your event.',
            'tour_lobby' => 'Back to the welcome desk.',
            'listen' => 'Listen', 'stop' => 'Stop', 'skip' => 'Skip', 'speaks' => 'is speaking',
        ],
        'id' => [
            'rooms' => 'Ini sesi ruang pelatihan. Ada :count ruangan, mulai dari :price per hari. Buka salah satu, nanti saya jelaskan pilihan penataannya — atau tanyakan apa saja kepada saya.',
            'rooms_one' => 'Ini sesi ruang pelatihan. Ada satu ruangan, mulai dari :price per hari. Buka ruangannya, nanti saya jelaskan pilihan penataannya — atau tanyakan apa saja kepada saya.',
            'type_size' => 'Ruangan ini berupa :type seluas :size m² untuk maksimal :capacity peserta.',
            'type_only' => 'Ruangan ini berupa :type untuk maksimal :capacity peserta.',
            'layouts' => 'Pilihan penataan: :layouts.',
            'floor' => 'Lokasinya di :floor.',
            'price' => 'Tarif mulai dari :price per hari, dan makin panjang programnya makin hemat tarif hariannya. Ketersediaan dan tarif final dikonfirmasi oleh tim kami.',
            'facilities' => 'Setiap program didukung :count fasilitas dan layanan, di antaranya :examples. Pilih salah satu untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'facilities_one' => 'Setiap program didukung :examples. Buka untuk membaca detailnya, atau tanyakan apa saja kepada saya.',
            'info_welcome' => 'Selamat datang di :venue.',
            'info_place' => 'Venue kami berada di :location.',
            'info_hours' => 'Hari pelatihan berjalan dari pukul :in sampai :out.',
            'info_more' => 'Di bawah ini ada alamat, aturan venue, dan pertanyaan yang sering diajukan — atau tanyakan langsung kepada saya.',
            'tour_rooms' => 'Menuju ruang pelatihan.',
            'tour_facilities' => 'Menuju fasilitas dan layanan.',
            'tour_info' => 'Menuju informasi venue.',
            'tour_team' => 'Menuju tim venue.',
            'tour_reservation' => 'Menuju meja booking untuk merencanakan acara Anda.',
            'tour_lobby' => 'Kembali ke meja penyambutan.',
            'listen' => 'Dengarkan', 'stop' => 'Berhenti', 'skip' => 'Lewati', 'speaks' => 'sedang berbicara',
        ],
        'ja' => [
            'rooms' => 'こちらは研修ルームのセッションです。:count つのルームを、1日 :price からご用意しています。ルームを開いていただければ、レイアウトをご案内します。ご質問もお気軽にどうぞ。',
            'rooms_one' => 'こちらは研修ルームのセッションです。1つのルームを、1日 :price からご用意しています。ルームを開いていただければ、レイアウトをご案内します。ご質問もお気軽にどうぞ。',
            'type_size' => ':typeで、広さは:size m²、最大:capacity名様までご利用いただけます。',
            'type_only' => ':typeで、最大:capacity名様までご利用いただけます。',
            'layouts' => 'レイアウト：:layouts。',
            'floor' => ':floorにございます。',
            'price' => '料金は1日 :price から。日数が長いほど1日あたりの料金がお得になります。空き状況と料金は、スタッフが最終確認いたします。',
            'facilities' => 'すべてのプログラムで、:count件の設備・サービスをご利用いただけます。:examples などです。選ぶと詳細をご覧いただけます。',
            'facilities_one' => 'すべてのプログラムで、:examples をご利用いただけます。詳細をご覧ください。',
            'info_welcome' => ':venue へようこそ。',
            'info_place' => '会場は:locationにございます。',
            'info_hours' => '研修日は:inから:outまでです。',
            'info_more' => '以下に、所在地、会場ルール、よくあるご質問をご案内しています。お気軽にお尋ねください。',
            'tour_rooms' => '研修ルームへご案内します。',
            'tour_facilities' => '設備とサービスへご案内します。',
            'tour_info' => '会場のご案内へ進みます。',
            'tour_team' => 'スタッフのもとへご案内します。',
            'tour_reservation' => '予約デスクで、イベントの計画をお手伝いします。',
            'tour_lobby' => 'ウェルカムデスクへ戻ります。',
            'listen' => '音声で聞く', 'stop' => '停止', 'skip' => 'スキップ', 'speaks' => '話しています',
        ],
    ];

    /**
     * Guest-facing names for the coded room values stored in the database.
     *
     * @var array<string, array{type: array<string, string>, layout: array<string, string>, amenity: array<string, string>}>
     */
    private const ROOM_TERMS = [
        'en' => [
            'type' => ['classroom' => 'classroom', 'workshop' => 'workshop lab', 'boardroom' => 'boardroom', 'auditorium' => 'hall', 'studio' => 'creative studio'],
            'layout' => ['theatre' => 'Theatre', 'classroom' => 'Classroom', 'u_shape' => 'U-shape', 'boardroom' => 'Boardroom', 'banquet' => 'Banquet', 'cluster' => 'Cluster'],
            'amenity' => ['projector' => 'HD projector', 'screen' => 'Projection screen', 'tv_display' => 'Large display', 'sound_system' => 'Sound system', 'wireless_mic' => 'Wireless microphones', 'whiteboard' => 'Whiteboards', 'flipchart' => 'Flipcharts', 'wifi' => 'Fibre Wi-Fi', 'air_conditioning' => 'Air conditioning', 'video_conference' => 'Video conferencing', 'podium' => 'Podium', 'stage' => 'Stage', 'lighting' => 'Stage lighting', 'power_outlets' => 'Power at every seat', 'breakout_area' => 'Breakout area', 'natural_light' => 'Natural light', 'laptop_rental' => 'Laptops on site', 'pantry' => 'Coffee pantry', 'recording' => 'Session recording', 'partition' => 'Movable partitions', 'sticky_wall' => 'Idea wall'],
        ],
        'id' => [
            'type' => ['classroom' => 'ruang kelas', 'workshop' => 'lab workshop', 'boardroom' => 'ruang rapat', 'auditorium' => 'aula', 'studio' => 'studio kreatif'],
            'layout' => ['theatre' => 'Theatre', 'classroom' => 'Classroom', 'u_shape' => 'U-shape', 'boardroom' => 'Boardroom', 'banquet' => 'Banquet', 'cluster' => 'Klaster'],
            'amenity' => ['projector' => 'Proyektor HD', 'screen' => 'Layar proyeksi', 'tv_display' => 'Layar besar', 'sound_system' => 'Sound system', 'wireless_mic' => 'Mikrofon nirkabel', 'whiteboard' => 'Papan tulis', 'flipchart' => 'Flipchart', 'wifi' => 'Wi-Fi fiber', 'air_conditioning' => 'AC', 'video_conference' => 'Video conference', 'podium' => 'Podium', 'stage' => 'Panggung', 'lighting' => 'Lampu panggung', 'power_outlets' => 'Stopkontak di tiap kursi', 'breakout_area' => 'Area diskusi', 'natural_light' => 'Cahaya alami', 'laptop_rental' => 'Laptop di lokasi', 'pantry' => 'Pantry kopi', 'recording' => 'Rekaman sesi', 'partition' => 'Sekat geser', 'sticky_wall' => 'Dinding ide'],
        ],
        'ja' => [
            'type' => ['classroom' => '教室タイプのルーム', 'workshop' => 'ワークショップラボ', 'boardroom' => '会議室', 'auditorium' => 'ホール', 'studio' => 'クリエイティブスタジオ'],
            'layout' => ['theatre' => 'シアター', 'classroom' => 'スクール', 'u_shape' => 'U字', 'boardroom' => 'ボードルーム', 'banquet' => 'バンケット', 'cluster' => 'アイランド'],
            'amenity' => ['projector' => 'HDプロジェクター', 'screen' => 'スクリーン', 'tv_display' => '大型ディスプレイ', 'sound_system' => '音響設備', 'wireless_mic' => 'ワイヤレスマイク', 'whiteboard' => 'ホワイトボード', 'flipchart' => 'フリップチャート', 'wifi' => '光Wi-Fi', 'air_conditioning' => 'エアコン', 'video_conference' => 'ビデオ会議', 'podium' => '演台', 'stage' => 'ステージ', 'lighting' => 'ステージ照明', 'power_outlets' => '全席に電源', 'breakout_area' => 'ディスカッションエリア', 'natural_light' => '自然光', 'laptop_rental' => '会場にノートPCあり', 'pantry' => 'コーヒーパントリー', 'recording' => 'セッション録画', 'partition' => '可動パーティション', 'sticky_wall' => 'アイデアウォール'],
        ],
    ];

    public function index(Request $request): View
    {
        $venues = Venue::where('public_status', 'published')->orderBy('name')->get();

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : ($venues->first()?->default_locale ?? 'id');

        return view('opening', [
            'venues' => $venues,
            'locale' => $locale,
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'opening' => $this->openingLabels($locale),
            'openingImage' => self::OPENING_PHOTO,
            'sessions' => self::SESSIONS,
        ]);
    }

    public function show(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.show', $this->stageData($venue, $locale, 'lobby'));
    }

    public function rooms(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.rooms-index', $this->stageData($venue, $locale, 'rooms'));
    }

    public function room(Request $request, string $venueSlug, string $roomSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'room');
        $rooms = $data['rooms'];
        $index = $rooms->search(fn (Room $room) => $room->slug === $roomSlug);

        abort_if($index === false, 404);

        return view('venue.room-detail', [
            ...$data,
            'room' => $rooms[$index],
            'roomIndex' => $index,
            'previousRoom' => $rooms[($index - 1 + $rooms->count()) % $rooms->count()],
            'nextRoom' => $rooms[($index + 1) % $rooms->count()],
        ]);
    }

    public function facilities(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.facilities-index', $this->stageData($venue, $locale, 'facilities'));
    }

    public function facility(Request $request, string $venueSlug, int $facilityId): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        $data = $this->stageData($venue, $locale, 'facility');
        $facilities = $data['facilities'];
        $index = $facilities->search(fn (VenueKnowledgeItem $item) => $item->id === $facilityId);

        abort_if($index === false, 404);

        return view('venue.facility-detail', [
            ...$data,
            'facility' => $facilities[$index],
            'facilityIndex' => $index,
            'previousFacility' => $facilities[($index - 1 + $facilities->count()) % $facilities->count()],
            'nextFacility' => $facilities[($index + 1) % $facilities->count()],
        ]);
    }

    public function info(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.info-index', $this->stageData($venue, $locale, 'info'));
    }

    public function team(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.team-index', $this->stageData($venue, $locale, 'team'));
    }

    public function reservationScene(Request $request, string $venueSlug): View
    {
        [$venue, $locale] = $this->resolveStage($request, $venueSlug);

        return view('venue.reservation-index', [
            ...$this->stageData($venue, $locale, 'reservation'),
            'preselectedRoom' => (string) $request->query('room', ''),
        ]);
    }

    /**
     * @return array{0: Venue, 1: string}
     */
    private function resolveStage(Request $request, string $venueSlug): array
    {
        $venue = Venue::where('slug', $venueSlug)->firstOrFail();

        abort_if(! $venue->isPublished(), 404);

        $locale = in_array($request->query('lang'), self::SUPPORTED_LOCALES, true)
            ? $request->query('lang')
            : $venue->default_locale;

        return [$venue, $locale];
    }

    /**
     * Everything the shared stage shell needs, for whichever session the
     * guest has moved on to.
     *
     * @return array<string, mixed>
     */
    private function stageData(Venue $venue, string $locale, string $scene): array
    {
        $rooms = $venue->rooms()
            ->where('is_active', true)
            ->with(['images' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->values();

        $facilities = $venue->knowledgeItems()->where('is_active', true)
            ->whereIn('category', ['facilities', 'catering', 'transport'])
            ->orderBy('sort_order')->get()->values();

        $labels = $this->labels($locale);
        $lobby = $this->lobbyLabels($locale);

        return [
            'venue' => $venue,
            'rooms' => $rooms,
            'locale' => $locale,
            'scene' => $scene,
            'session' => self::SESSIONS[['room' => 'rooms', 'facility' => 'facilities'][$scene] ?? $scene] ?? self::SESSIONS['lobby'],
            'sessionTotal' => count(self::SESSIONS),
            'backdrop' => $this->sceneBackdrop($scene),
            'menuItems' => $this->stageMenu($venue, $locale, $labels, $lobby),
            'supportedLocales' => self::SUPPORTED_LOCALES,
            'labels' => $labels,
            'lobby' => $lobby,
            'roomTerms' => self::ROOM_TERMS[$locale],
            'wizard' => $this->wizardLabels($venue, $locale),
            'narration' => self::NARRATION[$locale],
            'roomNarrations' => $this->roomNarrations($venue, $rooms, $locale),
            'staffLinks' => $this->handover->forStaff($venue, $locale),
            'today' => now($venue->timezone)->toDateString(),
            'infoItems' => $venue->knowledgeItems()->where('is_active', true)
                ->whereIn('category', ['general', 'policies', 'faq'])
                ->orderBy('sort_order')->get()->groupBy('category'),
            'facilities' => $facilities,
            'sceneNarrations' => $this->sceneNarrations($venue, $facilities, $locale),
            'programDays' => self::PROGRAM_DAYS,
        ];
    }

    /**
     * The photograph behind a scene, the coordinator posed in front of it,
     * plus the crop of her face used by the chat and narrator avatars.
     *
     * @return array{image: string, focus: string, focusMobile: string, character: string, pose: string, avatarImage: string, avatarZoom: string, avatarFocus: string}
     */
    private function sceneBackdrop(string $scene): array
    {
        $photo = self::SCENE_PHOTOS[['room' => 'rooms', 'facility' => 'facilities'][$scene] ?? $scene] ?? self::SCENE_PHOTOS['lobby'];

        $pose = self::SCENE_POSES[['room' => 'rooms'][$scene] ?? $scene] ?? 'standing';

        return [
            ...$photo,
            'character' => asset(self::CHARACTER_POSES[$pose]),
            'pose' => $pose,
            'avatarImage' => asset(self::AVATAR_IMAGE),
            'avatarZoom' => 'cover',
            'avatarFocus' => '50% 50%',
        ];
    }

    /**
     * The agenda strip. Every section is its own session, so choosing one
     * slides the projector screen down before the next session is shown.
     *
     * @param  array<string, string>  $labels
     * @param  array<string, string>  $lobby
     * @return list<array{key: string, session: string, label: string, href: string, tour: ?string, topic: string}>
     */
    private function stageMenu(Venue $venue, string $locale, array $labels, array $lobby): array
    {
        $tour = self::NARRATION[$locale];
        $url = fn (string $name) => route("venue.{$name}", ['venueSlug' => $venue->slug, 'lang' => $locale]);

        return [
            ['key' => 'rooms', 'session' => self::SESSIONS['rooms'], 'label' => $labels['rooms_heading'], 'href' => $url('rooms'), 'tour' => $tour['tour_rooms'], 'topic' => $labels['menu_rooms_q']],
            ['key' => 'facilities', 'session' => self::SESSIONS['facilities'], 'label' => $labels['menu_facilities'], 'href' => $url('facilities'), 'tour' => $tour['tour_facilities'], 'topic' => $labels['menu_facilities_q']],
            ['key' => 'info', 'session' => self::SESSIONS['info'], 'label' => $lobby['menu_info'], 'href' => $url('info'), 'tour' => $tour['tour_info'], 'topic' => $labels['menu_policies_q']],
            ['key' => 'reservation', 'session' => self::SESSIONS['reservation'], 'label' => $lobby['reservation'], 'href' => $url('reservation'), 'tour' => $tour['tour_reservation'], 'topic' => $lobby['reservation_q']],
            ['key' => 'team', 'session' => self::SESSIONS['team'], 'label' => $labels['menu_team'], 'href' => $url('team'), 'tour' => $tour['tour_team'], 'topic' => $labels['menu_team_q']],
        ];
    }

    /**
     * Intros for the facilities session and the venue information scene,
     * built only from stored venue data.
     *
     * @param  Collection<int, VenueKnowledgeItem>  $facilities
     * @return array{facilities: ?string, info: string}
     */
    private function sceneNarrations(Venue $venue, Collection $facilities, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $separator = $locale === 'ja' ? '、' : '; ';
        $time = fn (?string $value) => $value ? substr($value, 0, 5) : null;

        $intro = null;

        if ($facilities->isNotEmpty()) {
            $examples = $facilities->take(3)->map(fn ($item) => $item->translatedTitle($locale))->implode($separator);
            $intro = str_replace(
                [':count', ':examples'],
                [(string) $facilities->count(), $examples],
                $templates[$facilities->count() === 1 ? 'facilities_one' : 'facilities']
            );
        }

        $location = collect([$venue->city, $venue->country])->filter()->implode($locale === 'ja' ? '、' : ', ');
        $parts = [str_replace(':venue', $venue->name, $templates['info_welcome'])];

        if ($location !== '') {
            $parts[] = str_replace(':location', $location, $templates['info_place']);
        }

        if ($time($venue->opening_time) && $time($venue->closing_time)) {
            $parts[] = str_replace([':in', ':out'], [$time($venue->opening_time), $time($venue->closing_time)], $templates['info_hours']);
        }

        $parts[] = $templates['info_more'];

        return ['facilities' => $intro, 'info' => implode(' ', $parts)];
    }

    /**
     * @param  Collection<int, Room>  $rooms
     * @return array{rooms: ?string, room: array<string, string>}
     */
    private function roomNarrations(Venue $venue, Collection $rooms, string $locale): array
    {
        $templates = self::NARRATION[$locale];
        $terms = self::ROOM_TERMS[$locale];
        $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
        $sentence = fn (string $text) => preg_match('/[.!?。！？]$/u', $text) ? $text : $text.($locale === 'ja' ? '。' : '.');
        $join = $locale === 'ja' ? '、' : ', ';

        if ($rooms->isEmpty()) {
            return ['rooms' => null, 'room' => []];
        }

        $intro = str_replace(
            [':count', ':price'],
            [(string) $rooms->count(), $money($rooms->min('base_price'))],
            $templates[$rooms->count() === 1 ? 'rooms_one' : 'rooms']
        );

        $narrations = $rooms->mapWithKeys(function (Room $room) use ($templates, $terms, $money, $sentence, $locale, $join) {
            $parts = [$room->translatedName($locale).'.', filled($room->translatedDescription($locale)) ? $sentence(trim($room->translatedDescription($locale))) : null];

            $type = $terms['type'][$room->room_type] ?? $room->room_type;

            $parts[] = $room->size_sqm
                ? str_replace([':type', ':size', ':capacity'], [$type, (string) $room->size_sqm, (string) $room->capacity], $templates['type_size'])
                : str_replace([':type', ':capacity'], [$type, (string) $room->capacity], $templates['type_only']);

            $layouts = collect($room->seating_layouts ?? [])
                ->map(fn (array $option) => ($terms['layout'][$option['type']] ?? Str::headline($option['type'])).' '.$option['capacity'])
                ->implode($join);

            if ($layouts !== '') {
                $parts[] = str_replace(':layouts', $layouts, $templates['layouts']);
            }

            if ($room->floor_label) {
                $parts[] = str_replace(':floor', $room->floor_label, $templates['floor']);
            }

            $parts[] = str_replace(':price', $money($room->base_price), $templates['price']);

            return [$room->slug => implode(' ', array_filter($parts))];
        })->all();

        return ['rooms' => $intro, 'room' => $narrations];
    }

    /**
     * @return array<string, string>
     */
    private function openingLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'eyebrow' => 'Training venue · AI coordinator', 'welcome' => 'Great training starts', 'welcome_em' => 'in the right room.',
                'tagline' => 'Walk through the venue session by session with an AI coordinator that knows every room, seating layout, rate and house rule — for a half-day workshop or a week-long programme.',
                'enter' => 'Enter :name', 'empty' => 'The virtual venue is being prepared. Please come back soon.',
                'loading' => 'Setting up the room…', 'sound_on' => 'Sound on', 'sound_off' => 'Sound off', 'directory' => 'Programme agenda',
                'rooms' => 'Training rooms', 'facilities' => 'Facilities & services', 'reservation' => 'Plan an event', 'team' => 'Venue team',
            ],
            'ja' => [
                'eyebrow' => '研修会場 · AIコーディネーター', 'welcome' => '良い研修は、', 'welcome_em' => '最適な会場から。',
                'tagline' => 'すべてのルーム・レイアウト・料金・会場ルールを知るAIコーディネーターと、セッションごとに会場をご案内します。半日のワークショップから1週間のプログラムまで。',
                'enter' => ':name に入る', 'empty' => 'バーチャル会場は準備中です。しばらくしてからお越しください。',
                'loading' => '会場を準備しています…', 'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ', 'directory' => 'プログラム',
                'rooms' => '研修ルーム', 'facilities' => '設備・サービス', 'reservation' => 'イベントを計画する', 'team' => 'スタッフに相談',
            ],
            default => [
                'eyebrow' => 'Venue pelatihan · AI coordinator', 'welcome' => 'Pelatihan hebat dimulai', 'welcome_em' => 'dari ruangan yang tepat.',
                'tagline' => 'Jelajahi venue sesi demi sesi bersama AI Coordinator yang hafal setiap ruangan, penataan kursi, tarif, dan aturan venue — untuk workshop setengah hari maupun program seminggu penuh.',
                'enter' => 'Masuk ke :name', 'empty' => 'Venue virtual sedang dipersiapkan. Silakan kembali lagi nanti.',
                'loading' => 'Menyiapkan ruangan…', 'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati', 'directory' => 'Agenda program',
                'rooms' => 'Ruang pelatihan', 'facilities' => 'Fasilitas & layanan', 'reservation' => 'Rencanakan acara', 'team' => 'Tim venue',
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardLabels(Venue $venue, string $locale): array
    {
        $programRates = str_replace(
            [':multiday_days', ':multiday', ':intensive_days', ':intensive'],
            [(string) Venue::MULTIDAY_MIN_DAYS, (string) $venue->multiday_discount_percent, (string) Venue::INTENSIVE_MIN_DAYS, (string) $venue->intensive_discount_percent],
            match ($locale) {
                'en' => 'Programme rates: :multiday% off from :multiday_days days, :intensive% off from :intensive_days days.',
                'ja' => 'プログラム割引：:multiday_days日以上で:multiday%オフ、:intensive_days日以上で:intensive%オフ。',
                default => 'Tarif program: hemat :multiday% mulai :multiday_days hari, hemat :intensive% mulai :intensive_days hari.',
            }
        );

        return [...ReservationController::MESSAGES[$locale], 'program_hint' => $venue->multiday_discount_percent > 0 || $venue->intensive_discount_percent > 0 ? $programRates : '', ...match ($locale) {
            'en' => [
                'title' => 'Booking request', 'intro' => 'A few quick steps. Final availability is confirmed by our sales team.',
                'step_of' => 'Step :current of :total', 'steps' => ['Dates', 'Participants', 'Room', 'Your details', 'Summary'],
                'cal_prev' => 'Previous month', 'cal_next' => 'Next month', 'cal_pick_start' => 'Pick the first day of your programme.', 'cal_pick_end' => 'Now pick the last day (tap the same day again for a one-day event).', 'cal_none' => 'No dates are open online right now. Ask the coordinator or the venue team.', 'cal_full' => 'Fully booked', 'cal_choose' => 'Choose a date',
                'start_date' => 'First day', 'end_date' => 'Last day', 'days' => 'day(s)', 'participants' => 'Participants', 'rooms' => 'Rooms',
                'choose_room' => 'Choose a room', 'fits' => 'Up to :count participants per room', 'too_small' => 'Too small for your group',
                'layout' => 'Seating layout', 'layout_any' => 'No preference', 'layout_hint' => 'Holds :count in this layout',
                'catering' => 'Add catering (lunch and coffee breaks)', 'catering_short' => 'With catering', 'catering_price' => ':price per participant per day',
                'name' => 'Full name', 'organization' => 'Company / institution (optional)', 'event_title' => 'Programme title (optional)', 'event_placeholder' => 'e.g. Leadership workshop, batch 3',
                'contact_method' => 'How should we contact you?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Phone', 'email' => 'Email', 'contact_value' => 'Number or email address',
                'special' => 'Special request (optional)', 'special_placeholder' => 'e.g. early set-up at 07:00, extra projector, halal catering',
                'next' => 'Next', 'back' => 'Back', 'edit' => 'Edit', 'submit' => 'Send booking request', 'sending' => 'Sending…',
                'checking' => 'Checking availability…', 'estimated_total' => 'Estimated total', 'subtotal' => 'Subtotal', 'discount' => 'Programme discount',
                'group' => 'Group', 'room' => 'Room', 'dates' => 'Dates', 'programme' => 'Programme',
                'contact' => 'Contact', 'disclaimer' => 'This is a booking request, not a contract. Final availability and rates are confirmed by our sales team. No payment is taken now.',
                'available_instead' => 'Available for your dates instead:', 'done_title' => 'Request received', 'reference' => 'Your reference',
                'awaiting' => 'Awaiting confirmation from the venue team', 'done_hint' => 'Send your request to our team to speed up confirmation.',
                'send_whatsapp' => 'Send to WhatsApp', 'call_venue' => 'Call the venue team', 'email_venue' => 'Email the venue team', 'new_request' => 'New request',
                'error_network' => 'Connection problem. Your details are saved — please try again or contact the venue team.',
                'error_generic' => 'Something went wrong. Please try again or contact the venue team.', 'select_room' => 'Please choose a room.',
                'contact_staff' => 'Contact the venue team',
            ],
            'ja' => [
                'title' => '予約リクエスト', 'intro' => 'かんたんな手順です。空き状況は営業チームが最終確認いたします。',
                'step_of' => 'ステップ :current / :total', 'steps' => ['日程', '参加人数', 'ルーム', 'ご連絡先', '確認'],
                'cal_prev' => '前の月', 'cal_next' => '次の月', 'cal_pick_start' => 'プログラムの初日を選んでください。', 'cal_pick_end' => '続いて最終日を選んでください（1日だけの場合は同じ日をもう一度）。', 'cal_none' => '現在オンラインで受付中の日程はありません。コーディネーターまたはスタッフにご相談ください。', 'cal_full' => '満室', 'cal_choose' => '日付を選択',
                'start_date' => '初日', 'end_date' => '最終日', 'days' => '日間', 'participants' => '参加人数', 'rooms' => '部屋数',
                'choose_room' => 'ルームを選ぶ', 'fits' => '1室あたり最大:count名', 'too_small' => '人数に対応できません',
                'layout' => 'レイアウト', 'layout_any' => '指定なし', 'layout_hint' => 'このレイアウトで:count名',
                'catering' => 'ケータリングを追加（昼食・コーヒーブレイク）', 'catering_short' => 'ケータリング付き', 'catering_price' => '1名・1日 :price',
                'name' => 'お名前', 'organization' => '会社・団体名（任意）', 'event_title' => 'プログラム名（任意）', 'event_placeholder' => '例：リーダーシップ研修 第3期',
                'contact_method' => 'ご連絡方法',
                'whatsapp' => 'WhatsApp', 'phone' => '電話', 'email' => 'メール', 'contact_value' => '番号またはメールアドレス',
                'special' => 'ご要望（任意）', 'special_placeholder' => '例：7:00の早朝設営、プロジェクター追加、ハラールケータリング',
                'next' => '次へ', 'back' => '戻る', 'edit' => '編集', 'submit' => '予約リクエストを送信', 'sending' => '送信中…',
                'checking' => '空き状況を確認中…', 'estimated_total' => '概算合計', 'subtotal' => '小計', 'discount' => 'プログラム割引',
                'group' => '参加者', 'room' => 'ルーム', 'dates' => '日程', 'programme' => 'プログラム',
                'contact' => 'ご連絡先', 'disclaimer' => 'これは予約のリクエストであり、契約ではありません。空き状況と料金は営業チームが最終確認します。現時点でお支払いは発生しません。',
                'available_instead' => 'ご希望の日程で空いているルーム：', 'done_title' => 'リクエストを受け付けました', 'reference' => '受付番号',
                'awaiting' => '会場スタッフの確認待ち', 'done_hint' => 'リクエストをスタッフに送ると、確認がスムーズです。',
                'send_whatsapp' => 'WhatsAppで送る', 'call_venue' => '会場に電話', 'email_venue' => '会場にメール', 'new_request' => '新しいリクエスト',
                'error_network' => '接続に問題があります。入力内容は保存されています。もう一度お試しいただくか、スタッフにご連絡ください。',
                'error_generic' => 'エラーが発生しました。もう一度お試しいただくか、スタッフにご連絡ください。', 'select_room' => 'ルームを選択してください。',
                'contact_staff' => 'スタッフに連絡',
            ],
            default => [
                'title' => 'Permintaan booking', 'intro' => 'Hanya beberapa langkah singkat. Ketersediaan final dikonfirmasi oleh tim sales kami.',
                'step_of' => 'Langkah :current dari :total', 'steps' => ['Tanggal', 'Peserta', 'Ruangan', 'Data Anda', 'Ringkasan'],
                'cal_prev' => 'Bulan sebelumnya', 'cal_next' => 'Bulan berikutnya', 'cal_pick_start' => 'Pilih hari pertama program Anda.', 'cal_pick_end' => 'Sekarang pilih hari terakhir (ketuk hari yang sama lagi untuk acara satu hari).', 'cal_none' => 'Belum ada tanggal yang dibuka untuk permintaan online. Tanyakan ke coordinator atau tim venue.', 'cal_full' => 'Penuh', 'cal_choose' => 'Pilih tanggal',
                'start_date' => 'Hari pertama', 'end_date' => 'Hari terakhir', 'days' => 'hari', 'participants' => 'Jumlah peserta', 'rooms' => 'Jumlah ruangan',
                'choose_room' => 'Pilih ruangan', 'fits' => 'Maks. :count peserta per ruangan', 'too_small' => 'Tidak cukup untuk jumlah peserta Anda',
                'layout' => 'Penataan kursi', 'layout_any' => 'Bebas', 'layout_hint' => 'Muat :count peserta dengan penataan ini',
                'catering' => 'Tambah katering (makan siang dan coffee break)', 'catering_short' => 'Dengan katering', 'catering_price' => ':price per peserta per hari',
                'name' => 'Nama lengkap', 'organization' => 'Perusahaan / institusi (opsional)', 'event_title' => 'Nama acara (opsional)', 'event_placeholder' => 'mis. Workshop kepemimpinan, angkatan 3',
                'contact_method' => 'Bagaimana kami menghubungi Anda?',
                'whatsapp' => 'WhatsApp', 'phone' => 'Telepon', 'email' => 'Email', 'contact_value' => 'Nomor atau alamat email',
                'special' => 'Permintaan khusus (opsional)', 'special_placeholder' => 'mis. set-up pukul 07:00, proyektor tambahan, katering halal',
                'next' => 'Lanjut', 'back' => 'Kembali', 'edit' => 'Ubah', 'submit' => 'Kirim permintaan booking', 'sending' => 'Mengirim…',
                'checking' => 'Memeriksa ketersediaan…', 'estimated_total' => 'Perkiraan total', 'subtotal' => 'Subtotal', 'discount' => 'Diskon program',
                'group' => 'Peserta', 'room' => 'Ruangan', 'dates' => 'Tanggal', 'programme' => 'Acara',
                'contact' => 'Kontak', 'disclaimer' => 'Ini adalah permintaan booking, bukan kontrak. Ketersediaan dan tarif final dikonfirmasi oleh tim sales. Belum ada pembayaran yang diambil.',
                'available_instead' => 'Tersedia di tanggal Anda:', 'done_title' => 'Permintaan diterima', 'reference' => 'Nomor referensi',
                'awaiting' => 'Menunggu konfirmasi tim venue', 'done_hint' => 'Kirim permintaan ke tim kami agar konfirmasi lebih cepat.',
                'send_whatsapp' => 'Kirim ke WhatsApp', 'call_venue' => 'Telepon tim venue', 'email_venue' => 'Email tim venue', 'new_request' => 'Permintaan baru',
                'error_network' => 'Koneksi bermasalah. Data Anda tersimpan — coba lagi atau hubungi tim venue.',
                'error_generic' => 'Terjadi kesalahan. Coba lagi atau hubungi tim venue.', 'select_room' => 'Silakan pilih ruangan.',
                'contact_staff' => 'Hubungi tim venue',
            ],
        }];
    }

    /**
     * @return array<string, string>
     */
    private function lobbyLabels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'welcome' => 'Welcome to your', 'lobby' => 'virtual venue',
                'intro' => 'Training rooms, workshop labs and halls for a morning, a week or a whole programme. Move through the sessions — your AI coordinator goes with you.',
                'home' => 'Welcome', 'explore' => 'Programme agenda', 'reservation' => 'Plan an event', 'session' => 'Session', 'agenda' => 'Agenda',
                'reservation_intro' => 'Tell your AI Coordinator your dates and how many participants are coming. We will help you find a room and submit a booking request.',
                'reservation_q' => 'I would like to book a training room. Please help me check availability.',
                'start_booking' => 'Plan my event', 'available' => 'Coordinator on duty, 24/7',
                'assistant' => 'Your training coordinator', 'illustration' => 'AI illustration',
                'team_intro' => 'Need a person? Message us and the venue team — sales, event operations and technical support — will pick it up.',
                'empty' => 'Ask your AI Coordinator for more information.', 'back' => 'Back to the welcome desk',
                'hours_open' => 'Opens', 'hours_close' => 'Closes', 'location' => 'Find the venue',
                'connection_error' => 'Chat could not connect. Please reload to try again.',
                'rooms_empty' => 'Room information will be available soon. Please ask our team.',
                'menu_info' => 'Venue information', 'info_about' => 'About the venue', 'info_policies' => 'House rules', 'info_faq' => 'Frequently asked questions', 'info_tab_about' => 'About', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Address', 'info_hours' => 'Session hours', 'info_contact' => 'Contact', 'info_map' => 'Open in Maps', 'info_ask' => 'Ask about the venue',
                'facility_counter' => 'Facility', 'prev_facility' => 'Previous', 'next_facility' => 'Next', 'ask_facility' => 'Ask about this facility', 'back_facilities' => 'All facilities', 'open_facility' => 'Read more', 'ask_facility_q' => 'Tell me more about :name.',
                'loading' => 'Setting up the room…',
                'sound_on' => 'Sound on', 'sound_off' => 'Sound off',
                'room_scene' => 'Room details', 'room_counter' => 'Room', 'gallery' => 'Photo gallery', 'photo' => 'Photo',
                'no_photo' => 'Photos coming soon', 'prev_room' => 'Previous', 'next_room' => 'Next',
                'ask_room' => 'Ask about this room', 'reserve_room' => 'Request this room',
                'size' => 'Size', 'capacity' => 'Capacity', 'floor' => 'Location', 'type' => 'Room type', 'catering' => 'Catering', 'layouts' => 'Seating layouts',
                'catering_included' => 'Lunch & coffee breaks included', 'catering_yes' => 'Available', 'catering_no' => 'Not offered', 'amenities' => 'Equipment in the room',
                'participants_unit' => 'participants',
                'availability_note' => 'Final availability and rates are confirmed by the venue team.',
                'program_estimate' => 'A :days-day programme from about :price',
                'ask_room_q' => 'Tell me more about the :name.',
                'cta_rooms' => 'See the rooms', 'stat_from' => 'Per day from', 'stat_program' => 'Programme rates',
                'stat_program_value' => 'up to :percent% off', 'stat_hours' => 'Session hours',
            ],
            'ja' => [
                'welcome' => 'ようこそ', 'lobby' => 'バーチャル会場へ',
                'intro' => '午前だけでも、1週間でも、研修ルーム、ワークショップラボ、ホールをご用意。セッションを巡りながら、AIコーディネーターがご案内します。',
                'home' => 'ウェルカム', 'explore' => 'プログラム', 'reservation' => 'イベントを計画する', 'session' => 'セッション', 'agenda' => 'アジェンダ',
                'reservation_intro' => 'ご希望の日程と参加人数をAIコーディネーターにお伝えください。ルーム探しと予約リクエストをお手伝いします。',
                'reservation_q' => '研修ルームを予約したいです。空き状況を確認してください。',
                'start_booking' => 'イベントを計画する', 'available' => 'コーディネーター 24時間対応',
                'assistant' => '研修コーディネーター', 'illustration' => 'AIイラスト',
                'team_intro' => 'スタッフと話したい場合は、メッセージをお送りください。営業・イベント運営・技術サポートのチームが対応します。',
                'empty' => '詳しくはAIコーディネーターにお尋ねください。', 'back' => 'ウェルカムデスクに戻る',
                'hours_open' => '開始', 'hours_close' => '終了', 'location' => 'アクセス',
                'connection_error' => 'チャットに接続できませんでした。再読み込みしてください。',
                'rooms_empty' => 'ルームの情報は準備中です。スタッフにお尋ねください。',
                'menu_info' => '会場のご案内', 'info_about' => '会場について', 'info_policies' => '会場ルール', 'info_faq' => 'よくあるご質問', 'info_tab_about' => '概要', 'info_tab_faq' => 'FAQ',
                'info_address' => '所在地', 'info_hours' => '研修時間', 'info_contact' => 'お問い合わせ', 'info_map' => '地図で開く', 'info_ask' => '会場について聞く',
                'facility_counter' => '設備', 'prev_facility' => '前へ', 'next_facility' => '次へ', 'ask_facility' => 'この設備について聞く', 'back_facilities' => '設備一覧', 'open_facility' => '詳しく見る', 'ask_facility_q' => ':name について詳しく教えてください。',
                'loading' => '会場を準備しています…',
                'sound_on' => 'サウンドオン', 'sound_off' => 'サウンドオフ',
                'room_scene' => 'ルームのご案内', 'room_counter' => 'ルーム', 'gallery' => 'フォトギャラリー', 'photo' => '写真',
                'no_photo' => '写真は準備中です', 'prev_room' => '前へ', 'next_room' => '次へ',
                'ask_room' => 'このルームについて聞く', 'reserve_room' => 'このルームをリクエスト',
                'size' => '広さ', 'capacity' => '定員', 'floor' => '場所', 'type' => 'ルームタイプ', 'catering' => 'ケータリング', 'layouts' => 'レイアウト別定員',
                'catering_included' => '昼食・コーヒーブレイク込み', 'catering_yes' => '利用可', 'catering_no' => '提供なし', 'amenities' => 'ルームの設備',
                'participants_unit' => '名',
                'availability_note' => '空き状況と料金は、会場スタッフが最終確認いたします。',
                'program_estimate' => ':days日間のプログラムは約 :price から',
                'ask_room_q' => ':name について詳しく教えてください。',
                'cta_rooms' => 'ルームを見る', 'stat_from' => '1日あたり', 'stat_program' => 'プログラム割引',
                'stat_program_value' => '最大:percent%オフ', 'stat_hours' => '研修時間',
            ],
            default => [
                'welcome' => 'Selamat datang di', 'lobby' => 'venue virtual Anda',
                'intro' => 'Ruang kelas, lab workshop, dan aula untuk satu pagi, seminggu, atau satu program penuh. Telusuri sesi demi sesi — AI Coordinator ikut menemani.',
                'home' => 'Selamat datang', 'explore' => 'Agenda program', 'reservation' => 'Rencanakan acara', 'session' => 'Sesi', 'agenda' => 'Agenda',
                'reservation_intro' => 'Ceritakan tanggal dan jumlah peserta kepada AI Coordinator. Kami bantu carikan ruangan hingga pengajuan permintaan booking.',
                'reservation_q' => 'Saya ingin booking ruang pelatihan. Bantu saya cek ketersediaan.',
                'start_booking' => 'Rencanakan acara saya', 'available' => 'Coordinator siaga 24 jam',
                'assistant' => 'Koordinator pelatihan Anda', 'illustration' => 'Ilustrasi AI',
                'team_intro' => 'Butuh bicara dengan orang? Kirim pesan dan tim venue — sales, operasional acara, dan dukungan teknis — akan menindaklanjuti.',
                'empty' => 'Tanyakan informasi selengkapnya kepada AI Coordinator.', 'back' => 'Kembali ke meja penyambutan',
                'hours_open' => 'Mulai', 'hours_close' => 'Selesai', 'location' => 'Lokasi venue',
                'connection_error' => 'Chat belum tersambung. Muat ulang halaman untuk mencoba lagi.',
                'rooms_empty' => 'Informasi ruangan segera tersedia. Silakan tanyakan kepada tim kami.',
                'menu_info' => 'Informasi venue', 'info_about' => 'Tentang venue', 'info_policies' => 'Aturan venue', 'info_faq' => 'Pertanyaan yang sering diajukan', 'info_tab_about' => 'Tentang', 'info_tab_faq' => 'FAQ',
                'info_address' => 'Alamat', 'info_hours' => 'Jam sesi', 'info_contact' => 'Kontak', 'info_map' => 'Buka di Maps', 'info_ask' => 'Tanya tentang venue',
                'facility_counter' => 'Fasilitas', 'prev_facility' => 'Sebelumnya', 'next_facility' => 'Berikutnya', 'ask_facility' => 'Tanya tentang fasilitas ini', 'back_facilities' => 'Semua fasilitas', 'open_facility' => 'Selengkapnya', 'ask_facility_q' => 'Ceritakan lebih banyak tentang :name.',
                'loading' => 'Menyiapkan ruangan…',
                'sound_on' => 'Suara aktif', 'sound_off' => 'Suara mati',
                'room_scene' => 'Detail ruangan', 'room_counter' => 'Ruangan', 'gallery' => 'Galeri foto', 'photo' => 'Foto',
                'no_photo' => 'Foto segera tersedia', 'prev_room' => 'Sebelumnya', 'next_room' => 'Berikutnya',
                'ask_room' => 'Tanya tentang ruangan ini', 'reserve_room' => 'Ajukan booking ruangan ini',
                'size' => 'Luas', 'capacity' => 'Kapasitas', 'floor' => 'Lokasi', 'type' => 'Tipe ruangan', 'catering' => 'Katering', 'layouts' => 'Kapasitas per penataan',
                'catering_included' => 'Termasuk makan siang & coffee break', 'catering_yes' => 'Tersedia', 'catering_no' => 'Tidak tersedia', 'amenities' => 'Perlengkapan di ruangan',
                'participants_unit' => 'peserta',
                'availability_note' => 'Ketersediaan dan tarif final dikonfirmasi oleh tim venue.',
                'program_estimate' => 'Program :days hari mulai sekitar :price',
                'ask_room_q' => 'Ceritakan lebih banyak tentang :name.',
                'cta_rooms' => 'Lihat ruangan', 'stat_from' => 'Per hari mulai', 'stat_program' => 'Tarif program',
                'stat_program_value' => 'hemat hingga :percent%', 'stat_hours' => 'Jam sesi',
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function labels(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'from' => 'from',
                'per_day' => '/ day',
                'ask_ai' => 'Ask the AI Coordinator',
                'rooms_heading' => 'Training rooms',
                'chat_heading' => 'AI Coordinator',
                'chat_subtitle' => 'Ask about rooms, layouts or rates — on duty 24/7.',
                'chat_placeholder' => 'Ask about a room, a layout or a rate…',
                'chat_send' => 'Send',
                'chat_open' => 'Talk to the AI Coordinator',
                'chat_close' => 'Close conversation',
                'chat_intro' => "Hi! I'm the AI Coordinator of this training venue. Ask me about rooms, seating layouts, equipment, catering or how to plan your programme here.",
                'catering_included' => 'Catering included',
                'max_participants' => 'participants',
                'chat_no_catering' => 'Room only',
                'chat_day' => 'day(s)',
                'chat_discount' => 'programme discount',
                'chat_no_availability' => 'No availability for those dates.',
                'chat_booking_received' => 'Booking request received',
                'chat_reference' => 'Ref',
                'handed_over' => 'A member of the venue team has joined this conversation and will reply shortly.',
                'chat_status_sent' => 'Message sent',
                'chat_status_waiting' => 'Waiting for staff reply',
                'chat_status_replied' => 'Staff has replied',
                'chat_draft_title' => 'Unsaved message',
                'chat_draft_body' => 'This message has not been sent. Keep it as a draft?',
                'chat_draft_keep' => 'Keep typing',
                'chat_draft_discard' => 'Discard draft',
                'thinking' => 'Thinking…',
                'chat_error' => "I'm having trouble responding right now. Please try again or contact the venue team.", 'chat_slow' => 'You are sending messages very quickly. Please wait a moment and try again.', 'chat_retry' => 'Retry',
                'view_details' => 'View room',
                'room_details_question' => 'Show me more details and photos of :room',
                'book_now' => 'Request this room',
                'menu_heading' => 'Start here',
                'menu_rooms' => 'Find a room',
                'menu_rooms_q' => 'I would like to see the available training rooms.',
                'menu_facilities' => 'Facilities & services',
                'menu_facilities_q' => 'What equipment and services come with the rooms?',
                'menu_policies' => 'House rules & FAQ',
                'menu_policies_q' => 'What are the session hours, house rules and cancellation policies?',
                'menu_team' => 'Talk to the team',
                'menu_team_q' => 'I would like to speak with the venue team.',
            ],
            'ja' => [
                'from' => '',
                'per_day' => '〜 / 日',
                'ask_ai' => 'AIコーディネーターに聞く',
                'rooms_heading' => '研修ルーム',
                'chat_heading' => 'AIコーディネーター',
                'chat_subtitle' => 'ルーム・レイアウト・料金について24時間いつでもどうぞ。',
                'chat_placeholder' => 'ルーム、レイアウト、料金について質問…',
                'chat_send' => '送信',
                'chat_open' => 'AIコーディネーターに相談',
                'chat_close' => '会話を閉じる',
                'chat_intro' => 'こんにちは。この研修会場のAIコーディネーターです。ルーム、レイアウト、設備、ケータリング、プログラムの計画など、何でもお尋ねください。',
                'catering_included' => 'ケータリング込み',
                'max_participants' => '名まで',
                'chat_no_catering' => 'ルームのみ',
                'chat_day' => '日間',
                'chat_discount' => 'プログラム割引',
                'chat_no_availability' => 'ご希望の日程には空きがありません。',
                'chat_booking_received' => '予約リクエストを受け付けました',
                'chat_reference' => '受付番号',
                'handed_over' => 'スタッフがこの会話に参加しました。まもなく返信いたします。',
                'chat_status_sent' => 'メッセージを送信しました',
                'chat_status_waiting' => 'スタッフの返信を待っています',
                'chat_status_replied' => 'スタッフが返信しました',
                'chat_draft_title' => '未送信のメッセージ',
                'chat_draft_body' => 'このメッセージはまだ送信されていません。下書きとして残しますか？',
                'chat_draft_keep' => '入力を続ける',
                'chat_draft_discard' => '下書きを破棄',
                'thinking' => '入力中…',
                'chat_error' => '現在うまくお答えできません。もう一度お試しいただくか、スタッフにご連絡ください。', 'chat_slow' => 'メッセージが多すぎます。少し待ってからもう一度お試しください。', 'chat_retry' => '再試行',
                'view_details' => 'ルームを見る',
                'room_details_question' => ':room の詳細と写真を見せてください',
                'book_now' => 'このルームをリクエスト',
                'menu_heading' => 'ここから始める',
                'menu_rooms' => 'ルームを探す',
                'menu_rooms_q' => '利用できる研修ルームを見せてください。',
                'menu_facilities' => '設備・サービス',
                'menu_facilities_q' => 'ルームに付いている設備とサービスを教えてください。',
                'menu_policies' => '会場ルール・FAQ',
                'menu_policies_q' => '研修時間、会場ルール、キャンセルのポリシーを教えてください。',
                'menu_team' => 'スタッフに相談',
                'menu_team_q' => '会場のスタッフと話したいです。',
            ],
            default => [
                'from' => 'mulai dari',
                'per_day' => '/ hari',
                'ask_ai' => 'Tanya AI Coordinator',
                'rooms_heading' => 'Ruang Pelatihan',
                'chat_heading' => 'AI Coordinator',
                'chat_subtitle' => 'Tanyakan ruangan, penataan kursi, atau tarif — siaga 24 jam.',
                'chat_placeholder' => 'Tanya soal ruangan, penataan, atau tarif…',
                'chat_send' => 'Kirim',
                'chat_open' => 'Ngobrol dengan AI Coordinator',
                'chat_close' => 'Tutup percakapan',
                'chat_intro' => 'Halo! Saya AI Coordinator venue pelatihan ini. Tanyakan apa saja soal ruangan, penataan kursi, perlengkapan, katering, atau cara merencanakan program Anda di sini.',
                'catering_included' => 'Termasuk katering',
                'max_participants' => 'peserta',
                'chat_no_catering' => 'Ruangan saja',
                'chat_day' => 'hari',
                'chat_discount' => 'diskon program',
                'chat_no_availability' => 'Tidak ada ruangan tersedia untuk tanggal tersebut.',
                'chat_booking_received' => 'Permintaan booking diterima',
                'chat_reference' => 'Ref',
                'handed_over' => 'Tim venue telah bergabung dalam percakapan ini dan akan segera membalas.',
                'chat_status_sent' => 'Pesan terkirim',
                'chat_status_waiting' => 'Menunggu balasan staf',
                'chat_status_replied' => 'Staf telah membalas',
                'chat_draft_title' => 'Pesan belum dikirim',
                'chat_draft_body' => 'Pesan ini belum dikirim. Simpan sebagai draft?',
                'chat_draft_keep' => 'Lanjut mengetik',
                'chat_draft_discard' => 'Buang draft',
                'thinking' => 'Sedang mengetik…',
                'chat_error' => 'Saya sedang kesulitan menjawab. Silakan coba lagi atau hubungi tim venue.', 'chat_slow' => 'Pesan terlalu cepat. Mohon tunggu sebentar lalu coba lagi.', 'chat_retry' => 'Coba lagi',
                'view_details' => 'Lihat ruangan',
                'room_details_question' => 'Tunjukkan detail dan foto lengkap :room',
                'book_now' => 'Ajukan booking ruangan ini',
                'menu_heading' => 'Mulai dari sini',
                'menu_rooms' => 'Cari ruangan',
                'menu_rooms_q' => 'Saya ingin melihat pilihan ruang pelatihan yang tersedia.',
                'menu_facilities' => 'Fasilitas & layanan',
                'menu_facilities_q' => 'Perlengkapan dan layanan apa saja yang termasuk di ruangan?',
                'menu_policies' => 'Aturan & FAQ',
                'menu_policies_q' => 'Apa jam sesi, aturan venue, dan kebijakan pembatalan?',
                'menu_team' => 'Bicara dengan tim',
                'menu_team_q' => 'Saya ingin bicara dengan tim venue.',
            ],
        };
    }
}
