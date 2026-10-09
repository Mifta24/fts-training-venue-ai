<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoVenueSeeder extends Seeder
{
    /** Programmes can run for weeks, so inventory has to reach far enough ahead to quote them. */
    private const INVENTORY_DAYS = 180;

    public function run(): void
    {
        $owner = User::firstOrCreate(
            ['email' => 'owner@ftsvenue.test'],
            [
                'name' => 'Owner',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $description = [
            'id' => 'Venue pelatihan di Kuningan, Jakarta Selatan, dengan 5 jenis ruangan: ruang kelas, lab workshop, ruang rapat, aula 120 kursi, dan studio kreatif. Proyektor, sound system, Wi-Fi fiber, dan tim teknisi sudah termasuk — Anda cukup membawa peserta dan materi. 6 menit jalan kaki ke halte TransJakarta Kuningan.',
            'en' => 'A training venue in Kuningan, South Jakarta, with five kinds of rooms: classrooms, a workshop lab, a boardroom, a 120-seat hall and a creative studio. Projectors, sound, fibre Wi-Fi and a technical crew are included — just bring your participants and materials. A 6-minute walk to Kuningan TransJakarta stop.',
            'ja' => '南ジャカルタ・クニンガンにある研修会場。教室、ワークショップラボ、会議室、120席のホール、クリエイティブスタジオの5タイプをご用意しています。プロジェクター、音響、光Wi-Fi、技術スタッフ付きで、参加者と資料をお持ちいただくだけ。TransJakartaクニンガン停留所まで徒歩6分。',
        ];

        $venue = Venue::updateOrCreate(
            ['name' => 'FTS Training Venue AI'],
            [
                'slug' => Venue::where('name', 'FTS Training Venue AI')->value('slug') ?? Venue::generateUniqueSlug('FTS Training Venue AI'),
                'description' => $description['id'],
                'translations' => collect($description)->map(fn (string $text) => ['description' => $text])->all(),
                'address' => 'Jl. H.R. Rasuna Said Kav. 18, Kuningan',
                'city' => 'Jakarta Selatan',
                'country' => 'Indonesia',
                'latitude' => -6.2297,
                'longitude' => 106.8300,
                'phone' => '+62 21 5098 7700',
                'whatsapp' => '6281234567890',
                'email' => 'booking@ftsvenue.test',
                'timezone' => 'Asia/Jakarta',
                'currency' => 'IDR',
                'default_locale' => 'id',
                'opening_time' => '08:00',
                'closing_time' => '17:00',
                'multiday_discount_percent' => 10,
                'intensive_discount_percent' => 20,
                'cover_path' => 'https://images.unsplash.com/photo-1604328698692-f76ea9498e76?auto=format&fit=crop&w=1920&q=80',
                'public_status' => 'published',
            ]
        );

        $venue->users()->syncWithoutDetaching([
            $owner->id => ['role' => 'owner', 'status' => 'active'],
        ]);

        $sort = 0;
        foreach ($this->roomDefinitions() as $definition) {
            $images = $definition['images'];
            $totalRooms = $definition['total_rooms'];
            $blockedEvery = $definition['blocked_every'] ?? null;
            unset($definition['images'], $definition['total_rooms'], $definition['blocked_every']);

            $room = $venue->rooms()->updateOrCreate(
                ['slug' => $definition['slug']],
                [...$definition, 'sort_order' => $sort++, 'is_active' => true]
            );

            $room->images()->delete();
            foreach ($images as $imageSort => $image) {
                $room->images()->create([
                    'image_url' => $image['url'],
                    'tags' => $image['tags'],
                    'alt_text' => $image['alt'],
                    'sort_order' => $imageSort,
                ]);
            }

            $this->seedInventory($room, $totalRooms, $blockedEvery);
        }

        $this->seedKnowledgeBase($venue);

        $this->command?->info('Demo login: owner@ftsvenue.test — password: password');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function roomDefinitions(): array
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=1200&q=80";

        return [
            [
                'slug' => 'classroom-studio',
                'name' => 'Classroom Studio',
                'description' => 'Ruang kelas terang dengan meja lipat yang mudah ditata ulang, layar besar, dan papan tulis di dua sisi — pilihan utama untuk pelatihan 15–30 peserta.',
                'translations' => [
                    'id' => ['name' => 'Classroom Studio', 'description' => 'Ruang kelas terang dengan meja lipat yang mudah ditata ulang, layar besar, dan papan tulis di dua sisi — pilihan utama untuk pelatihan 15–30 peserta.'],
                    'en' => ['name' => 'Classroom Studio', 'description' => 'A bright classroom with folding tables that reset in minutes, a large screen and whiteboards on two walls — the everyday choice for trainings of 15 to 30 people.'],
                    'ja' => ['name' => 'クラスルーム スタジオ', 'description' => '数分で配置を変えられる折りたたみ式デスク、大型スクリーン、2面のホワイトボードを備えた明るい教室。15〜30名の研修の定番です。'],
                ],
                'room_type' => 'classroom',
                'size_sqm' => 60,
                'floor_label' => 'Lt. 2',
                'capacity' => 36,
                'seating_layouts' => [
                    ['type' => 'classroom', 'capacity' => 24],
                    ['type' => 'u_shape', 'capacity' => 18],
                    ['type' => 'theatre', 'capacity' => 36],
                    ['type' => 'cluster', 'capacity' => 24],
                ],
                'catering_included' => false,
                'catering_available' => true,
                'catering_price' => 185000,
                'base_price' => 2500000,
                'amenities' => ['projector', 'screen', 'whiteboard', 'flipchart', 'wifi', 'air_conditioning', 'power_outlets', 'natural_light'],
                'total_rooms' => 6,
                'images' => [
                    ['url' => $photo('1568992687947-868a62a9f521'), 'tags' => ['classroom'], 'alt' => 'Classroom Studio dengan meja peserta'],
                    ['url' => $photo('1524178232363-1fb2b075b655'), 'tags' => ['projector', 'screen'], 'alt' => 'Layar dan proyektor di Classroom Studio'],
                    ['url' => $photo('1591115765373-5207764f72e7'), 'tags' => ['classroom', 'cluster'], 'alt' => 'Sesi pelatihan dengan penataan klaster'],
                    ['url' => $photo('1522202176988-66273c2fd55f'), 'tags' => ['classroom'], 'alt' => 'Peserta mengerjakan latihan dengan laptop'],
                ],
            ],
            [
                'slug' => 'workshop-lab',
                'name' => 'Workshop Lab',
                'description' => 'Ruang hands-on dengan 24 stasiun kerja, stopkontak dan LAN di setiap meja, serta layar ganda untuk instruktur — untuk pelatihan IT, desain, dan analisis data.',
                'translations' => [
                    'id' => ['name' => 'Workshop Lab', 'description' => 'Ruang hands-on dengan 24 stasiun kerja, stopkontak dan LAN di setiap meja, serta layar ganda untuk instruktur — untuk pelatihan IT, desain, dan analisis data.'],
                    'en' => ['name' => 'Workshop Lab', 'description' => 'A hands-on room with 24 workstations, power and LAN at every desk and dual screens for the instructor — built for IT, design and data-analysis trainings.'],
                    'ja' => ['name' => 'ワークショップ ラボ', 'description' => '24台のワークステーション、全席に電源とLAN、講師用デュアルスクリーンを備えた実習ルーム。IT・デザイン・データ分析の研修向けです。'],
                ],
                'room_type' => 'workshop',
                'size_sqm' => 70,
                'floor_label' => 'Lt. 3',
                'capacity' => 24,
                'seating_layouts' => [
                    ['type' => 'classroom', 'capacity' => 24],
                    ['type' => 'cluster', 'capacity' => 20],
                ],
                'catering_included' => false,
                'catering_available' => true,
                'catering_price' => 185000,
                'base_price' => 3800000,
                'amenities' => ['projector', 'tv_display', 'laptop_rental', 'wifi', 'power_outlets', 'whiteboard', 'recording', 'air_conditioning'],
                'total_rooms' => 3,
                'images' => [
                    ['url' => $photo('1542744173-8e7e53415bb0'), 'tags' => ['workstation'], 'alt' => 'Stasiun kerja di Workshop Lab'],
                    ['url' => $photo('1519389950473-47ba0277781c'), 'tags' => ['laptop'], 'alt' => 'Peserta bekerja dengan laptop'],
                    ['url' => $photo('1552664730-d307ca884978'), 'tags' => ['whiteboard', 'sticky_wall'], 'alt' => 'Sesi workshop dengan catatan tempel'],
                    ['url' => $photo('1557804506-669a67965ba0'), 'tags' => ['whiteboard'], 'alt' => 'Papan tulis untuk sesi diskusi'],
                ],
            ],
            [
                'slug' => 'executive-boardroom',
                'name' => 'Executive Boardroom',
                'description' => 'Ruang rapat eksekutif dengan jendela setinggi plafon, meja oval 14 kursi, dan perangkat video conference — untuk coaching, strategic offsite, dan pelatihan kelompok kecil.',
                'translations' => [
                    'id' => ['name' => 'Executive Boardroom', 'description' => 'Ruang rapat eksekutif dengan jendela setinggi plafon, meja oval 14 kursi, dan perangkat video conference — untuk coaching, strategic offsite, dan pelatihan kelompok kecil.'],
                    'en' => ['name' => 'Executive Boardroom', 'description' => 'An executive boardroom with floor-to-ceiling windows, a 14-seat oval table and video-conferencing gear — for coaching, strategy offsites and small-group trainings.'],
                    'ja' => ['name' => 'エグゼクティブ ボードルーム', 'description' => '天井までの大きな窓、14席のオーバルテーブル、ビデオ会議機器を備えた役員会議室。コーチング、戦略合宿、少人数研修に。'],
                ],
                'room_type' => 'boardroom',
                'size_sqm' => 35,
                'floor_label' => 'Lt. 5',
                'capacity' => 14,
                'seating_layouts' => [
                    ['type' => 'boardroom', 'capacity' => 14],
                    ['type' => 'u_shape', 'capacity' => 12],
                ],
                'catering_included' => true,
                'catering_available' => false,
                'catering_price' => null,
                'base_price' => 3000000,
                'amenities' => ['tv_display', 'video_conference', 'whiteboard', 'wifi', 'pantry', 'natural_light', 'air_conditioning'],
                'total_rooms' => 2,
                'images' => [
                    ['url' => $photo('1431540015161-0bf868a2d407'), 'tags' => ['boardroom'], 'alt' => 'Executive Boardroom dengan jendela besar'],
                    ['url' => $photo('1497366811353-6870744d04b2'), 'tags' => ['boardroom'], 'alt' => 'Meja rapat panjang yang terang'],
                    ['url' => $photo('1517048676732-d65bc937f952'), 'tags' => ['meeting'], 'alt' => 'Diskusi tim di meja rapat'],
                ],
            ],
            [
                'slug' => 'grand-hall',
                'name' => 'Grand Hall',
                'description' => 'Aula 220 m² dengan panggung, lampu, dan sound system penuh — untuk seminar, town hall, dan kick-off perusahaan hingga 120 peserta. Bisa disekat menjadi dua ruang.',
                'translations' => [
                    'id' => ['name' => 'Grand Hall', 'description' => 'Aula 220 m² dengan panggung, lampu, dan sound system penuh — untuk seminar, town hall, dan kick-off perusahaan hingga 120 peserta. Bisa disekat menjadi dua ruang.'],
                    'en' => ['name' => 'Grand Hall', 'description' => 'A 220 m² hall with a stage, lighting and a full sound system — for seminars, town halls and company kick-offs of up to 120 participants. It can be split into two rooms.'],
                    'ja' => ['name' => 'グランド ホール', 'description' => 'ステージ、照明、フル音響を備えた220m²のホール。最大120名のセミナー、タウンホール、社内キックオフに。2室に分割することもできます。'],
                ],
                'room_type' => 'auditorium',
                'size_sqm' => 220,
                'floor_label' => 'Lt. 1',
                'capacity' => 120,
                'seating_layouts' => [
                    ['type' => 'theatre', 'capacity' => 120],
                    ['type' => 'classroom', 'capacity' => 70],
                    ['type' => 'banquet', 'capacity' => 80],
                    ['type' => 'u_shape', 'capacity' => 40],
                ],
                'catering_included' => false,
                'catering_available' => true,
                'catering_price' => 210000,
                'base_price' => 9500000,
                'amenities' => ['stage', 'lighting', 'sound_system', 'wireless_mic', 'projector', 'screen', 'podium', 'recording', 'partition', 'wifi', 'air_conditioning'],
                'total_rooms' => 1,
                'blocked_every' => 6,
                'images' => [
                    ['url' => $photo('1587825140708-dfaf72ae4b04'), 'tags' => ['theatre', 'stage'], 'alt' => 'Grand Hall dengan penataan theatre'],
                    ['url' => $photo('1519167758481-83f550bb49b3'), 'tags' => ['banquet'], 'alt' => 'Grand Hall dengan meja bundar'],
                    ['url' => $photo('1511578314322-379afb476865'), 'tags' => ['classroom', 'projector'], 'alt' => 'Grand Hall dengan layar ganda'],
                    ['url' => $photo('1560523160-754a9e25c68f'), 'tags' => ['stage', 'sound'], 'alt' => 'Pembicara di panggung Grand Hall'],
                ],
            ],
            [
                'slug' => 'creative-studio',
                'name' => 'Creative Studio',
                'description' => 'Ruang terbuka dengan furnitur yang bisa digeser, dinding ide, dan sudut santai — untuk design thinking, team building, dan sesi yang butuh banyak bergerak.',
                'translations' => [
                    'id' => ['name' => 'Creative Studio', 'description' => 'Ruang terbuka dengan furnitur yang bisa digeser, dinding ide, dan sudut santai — untuk design thinking, team building, dan sesi yang butuh banyak bergerak.'],
                    'en' => ['name' => 'Creative Studio', 'description' => 'An open room with movable furniture, an idea wall and relaxed corners — for design thinking, team building and sessions where people need to move.'],
                    'ja' => ['name' => 'クリエイティブ スタジオ', 'description' => '可動式の家具、アイデアウォール、くつろぎスペースを備えたオープンルーム。デザイン思考、チームビルディング、体を動かすセッションに。'],
                ],
                'room_type' => 'studio',
                'size_sqm' => 90,
                'floor_label' => 'Lt. 4',
                'capacity' => 40,
                'seating_layouts' => [
                    ['type' => 'cluster', 'capacity' => 32],
                    ['type' => 'theatre', 'capacity' => 40],
                    ['type' => 'u_shape', 'capacity' => 24],
                ],
                'catering_included' => false,
                'catering_available' => true,
                'catering_price' => 185000,
                'base_price' => 4500000,
                'amenities' => ['sticky_wall', 'whiteboard', 'flipchart', 'tv_display', 'breakout_area', 'pantry', 'natural_light', 'wifi', 'partition'],
                'total_rooms' => 2,
                'images' => [
                    ['url' => $photo('1604328698692-f76ea9498e76'), 'tags' => ['breakout'], 'alt' => 'Creative Studio yang terbuka dan terang'],
                    ['url' => $photo('1556761175-5973dc0f32e7'), 'tags' => ['cluster'], 'alt' => 'Sesi tim dengan layar dan sofa'],
                    ['url' => $photo('1558403194-611308249627'), 'tags' => ['breakout'], 'alt' => 'Sudut diskusi santai'],
                    ['url' => $photo('1515187029135-18ee286d815b'), 'tags' => ['team'], 'alt' => 'Tim berdiskusi dalam sesi workshop'],
                ],
            ],
        ];
    }

    private function seedInventory(Room $room, int $totalRooms, ?int $blockedEvery): void
    {
        $start = now()->startOfDay();

        for ($i = 0; $i < self::INVENTORY_DAYS; $i++) {
            $date = $start->copy()->addDays($i);

            // Companies book trainings on weekdays, so weekend days are the cheaper ones.
            $price = $date->isWeekend()
                ? (float) $room->base_price * 0.85
                : (float) $room->base_price;

            // Deterministic pseudo-occupancy so the demo shows realistic
            // partial availability instead of every date being wide open.
            // A room with a single hall is simply taken on some days.
            $booked = $blockedEvery !== null && ($i + $room->id) % $blockedEvery === 0
                ? $totalRooms
                : min(($i * 5 + $room->id * 3) % ($totalRooms + 1), $totalRooms - 1);

            // Match on the same value the date cast stores, so re-seeding
            // updates the existing day instead of colliding with it.
            RoomInventory::updateOrCreate(
                ['room_id' => $room->id, 'event_date' => $date],
                [
                    'total_rooms' => $totalRooms,
                    'booked_rooms' => $booked,
                    'price' => round($price, -3),
                ]
            );
        }
    }

    private function seedKnowledgeBase(Venue $venue): void
    {
        $photo = fn (string $id) => "https://images.unsplash.com/photo-{$id}?auto=format&fit=crop&w=800&q=80";

        $items = [
            [
                'category' => VenueKnowledgeItem::CATEGORY_GENERAL,
                'title' => 'Tentang FTS Training Venue AI',
                'body' => 'FTS Training Venue AI adalah venue pelatihan di Kuningan, Jakarta Selatan, dengan 14 ruangan dalam 5 tipe: ruang kelas, lab workshop, ruang rapat, aula 120 kursi, dan studio kreatif. Setiap ruangan sudah dilengkapi proyektor atau layar, Wi-Fi fiber, dan didampingi teknisi, sehingga trainer cukup datang membawa materi.',
                'translations' => [
                    'en' => ['title' => 'About FTS Training Venue AI', 'body' => 'FTS Training Venue AI is a training venue in Kuningan, South Jakarta, with 14 rooms in 5 types: classrooms, a workshop lab, a boardroom, a 120-seat hall and a creative studio. Every room comes with a projector or screen and fibre Wi-Fi, and a technician is on hand, so trainers only need to bring their materials.'],
                    'ja' => ['title' => 'FTS Training Venue AI について', 'body' => 'FTS Training Venue AI は南ジャカルタ・クニンガンにある研修会場で、教室、ワークショップラボ、会議室、120席のホール、クリエイティブスタジオの5タイプ・計14室をご用意しています。全室にプロジェクターまたはスクリーン、光Wi-Fiが付き、技術スタッフが常駐しているので、講師は資料をお持ちいただくだけです。'],
                ],
                'tags' => ['overview', 'introduction', 'training venue'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Jam sesi, set-up, dan pengembalian ruangan',
                'body' => 'Satu hari sewa berlangsung pukul 08:00–17:00. Set-up ruangan dan cek teknis dilakukan 30 menit sebelum pukul 08:00 tanpa biaya tambahan. Pemakaian di luar jam sesi (lembur) dikenakan Rp350.000 per jam per ruangan dan harus diajukan minimal sehari sebelumnya.',
                'translations' => [
                    'en' => ['title' => 'Session hours, set-up and hand-back', 'body' => 'A rental day runs from 8:00 AM to 5:00 PM. Room set-up and the technical check happen 30 minutes before 8:00 AM at no extra charge. Using a room outside session hours (overtime) costs IDR 350,000 per hour per room and must be requested at least a day ahead.'],
                    'ja' => ['title' => '研修時間・設営・原状回復', 'body' => '1日の利用は8:00〜17:00です。ルームの設営と技術チェックは8:00の30分前から無料で行えます。時間外利用（延長）は1室1時間あたり35万ルピアで、前日までにお申し込みください。'],
                ],
                'tags' => ['session hours', 'jam sesi', 'set-up', 'overtime', 'lembur', 'check-in'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Tarif program multi-hari dan intensif',
                'body' => 'Program 3 hari berturut-turut atau lebih otomatis mendapat diskon 10%, dan program 5 hari atau lebih (bootcamp) mendapat diskon 20% dari tarif harian. Untuk program di atas 30 hari atau kontrak tahunan perusahaan, tim sales akan membuatkan penawaran khusus.',
                'translations' => [
                    'en' => ['title' => 'Multi-day and intensive programme rates', 'body' => 'Programmes of 3 or more consecutive days automatically get 10% off the day rate, and programmes of 5 days or more (bootcamps) get 20% off. For programmes beyond 30 days or annual corporate contracts, our sales team prepares a tailored quote.'],
                    'ja' => ['title' => '複数日・集中プログラムの料金', 'body' => '連続3日以上のプログラムは1日料金から自動的に10%、5日以上（ブートキャンプ）は20%割引となります。30日を超えるプログラムや法人の年間契約は、営業チームが個別にお見積もりいたします。'],
                ],
                'tags' => ['multi-day', 'discount', 'diskon', 'bootcamp', 'corporate contract', 'program'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Kebijakan pembatalan dan perubahan jadwal',
                'body' => 'Pembatalan gratis hingga 7 hari sebelum hari pertama. Pembatalan 3–6 hari sebelumnya dikenakan biaya 50% dari sewa hari pertama, dan kurang dari 3 hari dikenakan 100% sewa hari pertama. Perubahan tanggal satu kali gratis bila diajukan minimal 7 hari sebelum hari pertama dan ruangan tersedia.',
                'translations' => [
                    'en' => ['title' => 'Cancellation and rescheduling', 'body' => 'Cancellation is free up to 7 days before the first day. Cancelling 3–6 days before costs 50% of the first day\'s rental, and less than 3 days before costs 100% of it. One date change is free when requested at least 7 days before the first day and the room is available.'],
                    'ja' => ['title' => 'キャンセル・日程変更', 'body' => '初日の7日前まではキャンセル無料です。3〜6日前は初日利用料の50%、3日前を過ぎると100%を頂戴します。日程変更は、初日の7日前までにご依頼いただき、ルームに空きがある場合に1回まで無料です。'],
                ],
                'tags' => ['cancellation', 'pembatalan', 'reschedule', 'refund'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_POLICIES,
                'title' => 'Aturan venue',
                'body' => 'Dilarang merokok di dalam gedung; area merokok ada di lantai dasar. Makanan dan minuman dari luar boleh dibawa untuk coffee break, namun katering di ruangan kelas dan lab hanya boleh berupa makanan ringan tanpa kuah. Peserta dan trainer wajib mengisi daftar hadir di meja registrasi untuk akses lift. Dilarang menempel di dinding dan kaca selain pada dinding ide Creative Studio.',
                'translations' => [
                    'en' => ['title' => 'House rules', 'body' => 'No smoking inside the building; a smoking area is on the ground floor. Outside food and drinks may be brought in for coffee breaks, but catering in the classrooms and the lab is limited to dry snacks. Participants and trainers sign in at the registration desk to use the lifts. Nothing may be stuck on walls or glass except on the Creative Studio idea wall.'],
                    'ja' => ['title' => '会場ルール', 'body' => '館内は禁煙で、喫煙所は1階にあります。コーヒーブレイク用の飲食物の持ち込みは可能ですが、教室とラボでは汁気のない軽食のみとなります。参加者と講師は、エレベーター利用のため受付で署名してください。クリエイティブスタジオのアイデアウォール以外の壁・ガラスへの貼り付けはできません。'],
                ],
                'tags' => ['house rules', 'aturan', 'smoking', 'food', 'registration'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Proyektor, layar, dan perangkat presentasi',
                'image_url' => $photo('1505373877841-8d25f7d46678'),
                'body' => 'Setiap ruangan dilengkapi proyektor HD atau layar besar dengan kabel HDMI, USB-C, dan adaptor nirkabel. Clicker presentasi dan kabel konverter tersedia di meja teknisi. Proyektor tambahan atau layar kedua bisa diminta dengan biaya Rp400.000 per hari.',
                'translations' => [
                    'en' => ['title' => 'Projectors, screens and presentation gear', 'body' => 'Every room has an HD projector or a large display with HDMI, USB-C and wireless casting. Presentation clickers and converter cables are available at the technician desk. An extra projector or a second screen can be requested for IDR 400,000 per day.'],
                    'ja' => ['title' => 'プロジェクター・スクリーン・プレゼン機器', 'body' => '全室にHDプロジェクターまたは大型ディスプレイがあり、HDMI、USB-C、ワイヤレス投影に対応しています。プレゼン用クリッカーと変換ケーブルは技術スタッフのデスクでお貸しします。追加のプロジェクターや2台目のスクリーンは1日40万ルピアで承ります。'],
                ],
                'tags' => ['projector', 'proyektor', 'screen', 'layar', 'hdmi', 'clicker'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Sound system dan mikrofon',
                'image_url' => $photo('1516280440614-37939bbacd81'),
                'body' => 'Grand Hall memiliki sound system penuh dengan empat mikrofon nirkabel, mixer, dan lampu panggung yang dioperasikan teknisi. Ruangan lain memakai speaker aktif dan satu mikrofon nirkabel. Rekaman audio sesi tersedia dengan biaya Rp500.000 per hari.',
                'translations' => [
                    'en' => ['title' => 'Sound system and microphones', 'body' => 'The Grand Hall has a full sound system with four wireless microphones, a mixer and stage lighting run by a technician. The other rooms use active speakers and one wireless microphone. Audio recording of a session is available for IDR 500,000 per day.'],
                    'ja' => ['title' => '音響設備とマイク', 'body' => 'グランドホールには、ワイヤレスマイク4本、ミキサー、ステージ照明を備えたフル音響があり、技術スタッフが操作します。その他のルームはアクティブスピーカーとワイヤレスマイク1本です。セッションの音声録音は1日50万ルピアで承ります。'],
                ],
                'tags' => ['sound', 'audio', 'microphone', 'mikrofon', 'speaker', 'recording'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Wi-Fi fiber dan jaringan',
                'image_url' => $photo('1519389950473-47ba0277781c'),
                'body' => 'Wi-Fi fiber 1 Gbps tersedia di seluruh gedung dan mampu melayani 120 perangkat sekaligus di Grand Hall. Workshop Lab memiliki LAN kabel dan stopkontak di setiap meja. Jaringan khusus dengan IP tetap untuk demo atau ujian online bisa diatur dengan pemberitahuan 3 hari sebelumnya.',
                'translations' => [
                    'en' => ['title' => 'Fibre Wi-Fi and network', 'body' => '1 Gbps fibre Wi-Fi covers the whole building and handles 120 devices at once in the Grand Hall. The Workshop Lab has wired LAN and a power outlet at every desk. A dedicated network with a fixed IP for demos or online exams can be arranged with 3 days\' notice.'],
                    'ja' => ['title' => '光Wi-Fiとネットワーク', 'body' => '1Gbpsの光Wi-Fiが館内全域で使え、グランドホールでは120台の同時接続に対応します。ワークショップラボは全席に有線LANと電源があります。デモやオンライン試験用の固定IP付き専用ネットワークは、3日前までのご連絡で設定できます。'],
                ],
                'tags' => ['wifi', 'internet', 'lan', 'bandwidth', 'network'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Whiteboard, flipchart, dan alat tulis',
                'image_url' => $photo('1557804506-669a67965ba0'),
                'body' => 'Papan tulis, flipchart, spidol, dan kertas tempel sudah disediakan di setiap ruangan tanpa biaya. Set alat tulis peserta (buku catatan dan pulpen) bisa dipesan Rp25.000 per peserta.',
                'translations' => [
                    'en' => ['title' => 'Whiteboards, flipcharts and stationery', 'body' => 'Whiteboards, flipcharts, markers and sticky notes come with every room at no charge. Participant stationery sets (notebook and pen) can be ordered for IDR 25,000 per participant.'],
                    'ja' => ['title' => 'ホワイトボード・フリップチャート・文具', 'body' => 'ホワイトボード、フリップチャート、マーカー、付箋は全室に無料でご用意しています。参加者用の文具セット（ノートとペン）は1名2.5万ルピアで承ります。'],
                ],
                'tags' => ['whiteboard', 'flipchart', 'stationery', 'atk', 'training kit'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FACILITIES,
                'title' => 'Teknisi dan tim acara di lokasi',
                'image_url' => $photo('1478737270239-2f02b77fc618'),
                'body' => 'Satu teknisi AV standby sepanjang jam sesi untuk membantu set-up, proyektor, dan mikrofon. Meja registrasi dan petugas acara tersedia untuk program di atas 40 peserta tanpa biaya tambahan; untuk program lebih kecil, petugas bisa dipesan Rp600.000 per hari.',
                'translations' => [
                    'en' => ['title' => 'On-site technician and event crew', 'body' => 'One AV technician is on standby throughout session hours for set-up, projectors and microphones. A registration desk and event crew are included for programmes above 40 participants; for smaller ones a crew member can be added for IDR 600,000 per day.'],
                    'ja' => ['title' => '常駐の技術スタッフとイベントクルー', 'body' => 'AV技術スタッフ1名が研修時間中ずっと待機し、設営、プロジェクター、マイクをサポートします。40名を超えるプログラムでは、受付デスクとイベントクルーが追加料金なしで付きます。それ以下の規模では、クルーを1日60万ルピアで追加できます。'],
                ],
                'tags' => ['technician', 'teknisi', 'support', 'event crew', 'registration', 'on-site'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Katering: makan siang dan coffee break',
                'image_url' => $photo('1555244162-803834f70033'),
                'body' => 'Paket katering Rp185.000 per peserta per hari (Rp210.000 untuk Grand Hall) sudah mencakup makan siang prasmanan, dua kali coffee break, dan air mineral. Menu halal dan vegetarian tersedia bila diminta 3 hari sebelumnya. Boardroom sudah termasuk coffee break dan makan siang di tarif sewanya.',
                'translations' => [
                    'en' => ['title' => 'Catering: lunch and coffee breaks', 'body' => 'The catering package costs IDR 185,000 per participant per day (IDR 210,000 for the Grand Hall) and covers a buffet lunch, two coffee breaks and mineral water. Halal and vegetarian menus are available when requested 3 days ahead. The Boardroom already includes coffee breaks and lunch in its rate.'],
                    'ja' => ['title' => 'ケータリング：昼食とコーヒーブレイク', 'body' => 'ケータリングパッケージは1名1日18.5万ルピア（グランドホールは21万ルピア）で、ビュッフェ昼食、コーヒーブレイク2回、ミネラルウォーターが含まれます。ハラール・ベジタリアン対応は3日前までのご依頼で承ります。ボードルームは利用料にコーヒーブレイクと昼食が含まれています。'],
                ],
                'tags' => ['catering', 'katering', 'lunch', 'coffee break', 'halal', 'makan siang'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_CATERING,
                'title' => 'Penginapan untuk peserta dari luar kota',
                'image_url' => $photo('1551882547-ff40c63fe5fa'),
                'body' => 'Kami bekerja sama dengan hotel bintang 4 yang berjarak 4 menit berkendara dengan tarif korporat mulai Rp850.000 per kamar per malam termasuk sarapan, untuk minimal 10 kamar-malam. Antar-jemput hotel ke venue tersedia pada pukul 07:15 dan 17:30.',
                'translations' => [
                    'en' => ['title' => 'Accommodation for out-of-town participants', 'body' => 'We partner with a 4-star hotel 4 minutes\' drive away, with corporate rates from IDR 850,000 per room per night including breakfast, for a minimum of 10 room-nights. A hotel-to-venue shuttle runs at 7:15 AM and 5:30 PM.'],
                    'ja' => ['title' => '遠方からの参加者の宿泊', 'body' => '車で4分の4つ星ホテルと提携しており、朝食付きで1室1泊85万ルピアからの法人料金をご利用いただけます（10室泊以上）。ホテルと会場を結ぶシャトルが7:15と17:30に運行します。'],
                ],
                'tags' => ['hotel', 'accommodation', 'penginapan', 'menginap', 'shuttle'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'TransJakarta, MRT, dan parkir',
                'image_url' => $photo('1590674899484-d5640e854abe'),
                'body' => 'Halte TransJakarta Kuningan berjarak 6 menit jalan kaki, dan stasiun MRT Setiabudi Astra sekitar 10 menit berkendara. Parkir basement menampung 80 mobil dan 6 bus; 10 slot gratis untuk setiap hari sewa, slot tambahan Rp25.000 per mobil per hari. Bus peserta bisa menurunkan penumpang di drop-off depan.',
                'translations' => [
                    'en' => ['title' => 'TransJakarta, MRT and parking', 'body' => 'Kuningan TransJakarta stop is a 6-minute walk, and Setiabudi Astra MRT station is about 10 minutes by car. The basement car park holds 80 cars and 6 buses; 10 spaces are free for every rental day and extra spaces are IDR 25,000 per car per day. Participant buses can drop off at the front entrance.'],
                    'ja' => ['title' => 'TransJakarta・MRT・駐車場', 'body' => 'TransJakartaクニンガン停留所まで徒歩6分、MRTセティアブディ・アストラ駅まで車で約10分です。地下駐車場は乗用車80台・バス6台を収容し、利用日ごとに10台分が無料、追加は1台1日2.5万ルピアです。参加者のバスは正面の車寄せで降車できます。'],
                ],
                'tags' => ['transjakarta', 'mrt', 'parking', 'parkir', 'bus', 'transit'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_TRANSPORT,
                'title' => 'Dari bandara ke venue',
                'image_url' => $photo('1436491865332-7a61a109cc05'),
                'body' => 'Dari Bandara Soekarno-Hatta ke venue sekitar 45–75 menit tergantung lalu lintas. Antar-jemput bandara untuk rombongan bisa dipesan dengan biaya Rp450.000 per mobil sekali jalan atau Rp1.800.000 per bus; pesan minimal 3 hari sebelumnya dengan jadwal penerbangan.',
                'translations' => [
                    'en' => ['title' => 'From the airport to the venue', 'body' => 'From Soekarno-Hatta Airport to the venue it is about 45–75 minutes depending on traffic. Group airport transfers can be booked for IDR 450,000 per car one way or IDR 1,800,000 per bus; please book at least 3 days ahead with the flight schedule.'],
                    'ja' => ['title' => '空港から会場へ', 'body' => 'スカルノ・ハッタ空港から会場までは交通状況により約45〜75分です。団体の空港送迎は、乗用車片道45万ルピア、バス180万ルピアで承ります。3日前までにフライトスケジュールとあわせてご予約ください。'],
                ],
                'tags' => ['airport transfer', 'soekarno-hatta', 'bandara', 'shuttle'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bisakah penataan kursi diubah di tengah program?',
                'body' => 'Bisa. Tim kami mengubah penataan kursi (misalnya dari classroom ke cluster) saat coffee break atau makan siang tanpa biaya, asalkan layout yang diminta termasuk dalam pilihan ruangan tersebut. Perubahan besar di tengah sesi dikenakan biaya Rp500.000 per perubahan.',
                'translations' => [
                    'en' => ['title' => 'Can the seating layout change during the programme?', 'body' => 'Yes. Our crew switches the seating (for example from classroom to cluster) during a coffee break or lunch at no charge, as long as the requested layout is one the room offers. A major change in the middle of a session costs IDR 500,000 per change.'],
                    'ja' => ['title' => 'プログラム中にレイアウトを変更できますか？', 'body' => 'はい。ルームが対応しているレイアウトであれば、コーヒーブレイクや昼食の間にスタッフが無料で切り替えます（例：スクールからアイランドへ）。セッション中の大幅な変更は1回50万ルピアです。'],
                ],
                'tags' => ['layout', 'seating', 'penataan', 'faq'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Apakah ada deposit dan bagaimana pembayarannya?',
                'body' => 'Booking dikonfirmasi setelah uang muka 30% dibayar maksimal 3 hari setelah konfirmasi tim kami. Pelunasan dilakukan paling lambat 3 hari sebelum hari pertama. Deposit kerusakan Rp2.000.000 per program dikembalikan dalam 7 hari kerja setelah hari terakhir bila tidak ada kerusakan. Pembayaran lewat transfer bank dengan faktur untuk perusahaan.',
                'translations' => [
                    'en' => ['title' => 'Is there a deposit, and how do I pay?', 'body' => 'A booking is confirmed once a 30% down payment is made within 3 days of our team\'s confirmation. The balance is due at least 3 days before the first day. A damage deposit of IDR 2,000,000 per programme is refunded within 7 working days after the last day if nothing is damaged. Payment is by bank transfer, with an invoice for companies.'],
                    'ja' => ['title' => 'デポジットと支払い方法は？', 'body' => 'スタッフの確認から3日以内に30%の手付金をお支払いいただくと、予約が確定します。残金は初日の3日前までにお支払いください。損傷デポジットはプログラムごとに200万ルピアで、破損がなければ最終日から7営業日以内に返金します。お支払いは銀行振込で、法人には請求書を発行します。'],
                ],
                'tags' => ['deposit', 'payment', 'pembayaran', 'dp', 'invoice', 'faq'],
            ],
            [
                'category' => VenueKnowledgeItem::CATEGORY_FAQ,
                'title' => 'Bisakah kami memakai katering dari luar?',
                'body' => 'Bisa untuk coffee break, dengan biaya layanan Rp15.000 per peserta per hari dan makanan harus tiba sebelum pukul 11:30. Untuk makan siang, kami mewajibkan katering venue agar kebersihan dan keamanan pangan terjaga, kecuali untuk program di atas 80 peserta yang bisa dibicarakan dengan tim sales.',
                'translations' => [
                    'en' => ['title' => 'Can we bring an outside caterer?', 'body' => 'For coffee breaks, yes, with a service fee of IDR 15,000 per participant per day, and the food must arrive before 11:30 AM. For lunch we require the venue\'s own catering to keep hygiene and food safety under control, except for programmes above 80 participants, which can be discussed with our sales team.'],
                    'ja' => ['title' => '外部のケータリングは利用できますか？', 'body' => 'コーヒーブレイクは可能で、1名1日1.5万ルピアのサービス料がかかり、11:30までに搬入が必要です。昼食は衛生管理のため会場のケータリングをご利用いただきます。80名を超えるプログラムは営業チームにご相談ください。'],
                ],
                'tags' => ['outside catering', 'katering luar', 'faq', 'food'],
            ],
        ];

        $sort = 0;
        foreach ($items as $item) {
            $venue->knowledgeItems()->updateOrCreate(
                ['title' => $item['title']],
                [
                    'category' => $item['category'],
                    'body' => $item['body'],
                    'translations' => $item['translations'],
                    'tags' => $item['tags'],
                    'image_url' => $item['image_url'] ?? null,
                    'is_active' => true,
                    'sort_order' => $sort++,
                ]
            );
        }
    }
}
