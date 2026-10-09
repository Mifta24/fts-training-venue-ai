<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Room;
use App\Models\RoomInventory;
use App\Models\Venue;
use App\Services\Reservation\ReservationHandover;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    private const SUPPORTED_LOCALES = ['id', 'en', 'ja'];

    /** How far ahead the date picker looks for open days. */
    private const AVAILABILITY_DAYS = 366;

    private const PHONE_PATTERN = '/^\+?[0-9\s\-().]{6,20}$/';

    /**
     * Guest-facing validation messages, keyed by locale.
     *
     * @var array<string, array<string, string>>
     */
    public const MESSAGES = [
        'en' => [
            'invalid' => 'Please check this field.',
            'start_past' => 'The first day cannot be in the past.',
            'end_after' => 'The last day cannot be before the first day.',
            'too_long' => 'Online requests cover up to :max days. For a longer programme, please contact our sales team.',
            'room_unknown' => 'Please choose a room.',
            'capacity' => 'This room does not fit that many participants. Choose a larger room or add another room.',
            'unavailable' => 'This room is not available for those dates.',
            'contact_email' => 'Please enter a valid email address.',
            'contact_phone' => 'Please enter a valid phone or WhatsApp number.',
        ],
        'id' => [
            'invalid' => 'Mohon periksa kolom ini.',
            'start_past' => 'Hari pertama tidak boleh di masa lalu.',
            'end_after' => 'Hari terakhir tidak boleh sebelum hari pertama.',
            'too_long' => 'Permintaan online maksimal :max hari. Untuk program yang lebih panjang, hubungi tim sales kami.',
            'room_unknown' => 'Silakan pilih ruangan.',
            'capacity' => 'Ruangan ini tidak cukup untuk jumlah peserta tersebut. Pilih ruangan yang lebih besar atau tambah ruangan.',
            'unavailable' => 'Ruangan ini tidak tersedia pada tanggal tersebut.',
            'contact_email' => 'Masukkan alamat email yang valid.',
            'contact_phone' => 'Masukkan nomor telepon atau WhatsApp yang valid.',
        ],
        'ja' => [
            'invalid' => 'この項目をご確認ください。',
            'start_past' => '初日は過去にできません。',
            'end_after' => '最終日は初日より前にできません。',
            'too_long' => 'オンラインでのご依頼は最大:max日間までです。それ以上のプログラムは営業チームにご相談ください。',
            'room_unknown' => 'ルームを選択してください。',
            'capacity' => 'このルームでは参加人数に対応できません。広いルームを選ぶか、部屋数を増やしてください。',
            'unavailable' => 'この日程では、このルームはご利用いただけません。',
            'contact_email' => '有効なメールアドレスを入力してください。',
            'contact_phone' => '有効な電話番号またはWhatsApp番号を入力してください。',
        ],
    ];

    public function __construct(
        private readonly ReservationService $reservations,
        private readonly ReservationHandover $handover,
    ) {}

    /**
     * The days on which at least one room still has a free slot, so the date
     * picker can grey out the days that are fully booked.
     */
    public function availability(string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $today = CarbonImmutable::now($venue->timezone)->startOfDay();
        $until = $today->addDays(self::AVAILABILITY_DAYS);

        $dates = RoomInventory::whereIn('room_id', $venue->rooms()->where('is_active', true)->select('id'))
            ->whereRaw('total_rooms > booked_rooms')
            ->whereDate('event_date', '>=', $today->toDateString())
            ->whereDate('event_date', '<=', $until->toDateString())
            ->distinct()
            ->orderBy('event_date')
            ->get(['event_date'])
            ->map(fn (RoomInventory $day) => $day->event_date->toDateString())
            ->unique()
            ->values();

        return response()->json([
            'today' => $today->toDateString(),
            'until' => $until->toDateString(),
            'dates' => $dates,
        ]);
    }

    public function quote(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);

        $data = $request->validate($this->bookingRules($venue), $this->validationMessages($locale));
        [$room, $startDate, $endDate, $rooms, $catering] = $this->resolveBooking($venue, $data, $locale);

        $quote = $this->reservations->quote($room, $startDate, $endDate, $rooms, $catering, (int) $data['participants']);

        if (! $quote) {
            return $this->unavailable($venue, $room, $data, $locale);
        }

        return response()->json([
            'available' => true,
            'days' => $quote['days'],
            'room_total' => $quote['room_total'],
            'catering_total' => $quote['catering_total'],
            'subtotal' => $quote['subtotal'],
            'discount_percent' => $quote['discount_percent'],
            'discount_total' => $quote['discount_total'],
            'grand_total' => $quote['grand_total'],
            'currency' => $venue->currency,
        ]);
    }

    public function store(Request $request, string $venueSlug): JsonResponse
    {
        $venue = $this->publishedVenue($venueSlug);
        $locale = $this->locale($request, $venue);
        $messages = self::MESSAGES[$locale];

        $data = $request->validate([
            ...$this->bookingRules($venue),
            'guest_name' => ['required', 'string', 'max:100'],
            'organization' => ['nullable', 'string', 'max:120'],
            'event_title' => ['nullable', 'string', 'max:150'],
            'contact_type' => ['required', Rule::in(['whatsapp', 'phone', 'email'])],
            'contact_value' => ['required', 'string', 'max:120'],
            'special_request' => ['nullable', 'string', 'max:500'],
            'guest_token' => ['nullable', 'uuid'],
        ], $this->validationMessages($locale));

        $isEmail = $data['contact_type'] === 'email';

        if ($isEmail && ! filter_var($data['contact_value'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_email']]);
        }

        if (! $isEmail && ! preg_match(self::PHONE_PATTERN, $data['contact_value'])) {
            throw ValidationException::withMessages(['contact_value' => $messages['contact_phone']]);
        }

        [$room, $startDate, $endDate, $rooms, $catering] = $this->resolveBooking($venue, $data, $locale);

        $conversation = isset($data['guest_token'])
            ? Conversation::where('venue_id', $venue->id)->where('guest_token', $data['guest_token'])->first()
            : null;

        $booking = $this->reservations->createRequest($venue, $room, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'participants' => (int) $data['participants'],
            'rooms' => $rooms,
            'seating_layout' => $data['seating_layout'] ?? null,
            'catering' => $catering,
            'guest_name' => $data['guest_name'],
            'guest_email' => $isEmail ? $data['contact_value'] : null,
            'guest_phone' => $isEmail ? null : $data['contact_value'],
            'contact_type' => $data['contact_type'],
            'locale' => $locale,
            'organization' => $data['organization'] ?? null,
            'event_title' => $data['event_title'] ?? null,
            'notes' => $data['special_request'] ?? null,
        ], $conversation);

        if (! $booking) {
            return $this->unavailable($venue, $room, $data, $locale);
        }

        return response()->json([
            'reference' => $booking->reference,
            'status' => $booking->status,
            'total' => (float) $booking->total_price,
            'currency' => $venue->currency,
            'handover' => $this->handover->forBooking($venue, $booking, $locale),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function bookingRules(Venue $venue): array
    {
        $today = now($venue->timezone)->toDateString();

        return [
            'room_slug' => ['required', 'string', 'max:120'],
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'participants' => ['required', 'integer', 'min:1', 'max:500'],
            'rooms' => ['required', 'integer', 'min:1', 'max:10'],
            'seating_layout' => ['nullable', Rule::in(Room::SEATING_LAYOUTS)],
            'catering' => ['nullable', 'boolean'],
            'locale' => ['nullable', Rule::in(self::SUPPORTED_LOCALES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function validationMessages(string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        return [
            'required' => $messages['invalid'],
            'date_format' => $messages['invalid'],
            'integer' => $messages['invalid'],
            'min' => $messages['invalid'],
            'max' => $messages['invalid'],
            'boolean' => $messages['invalid'],
            'in' => $messages['invalid'],
            'uuid' => $messages['invalid'],
            'start_date.after_or_equal' => $messages['start_past'],
            'end_date.after_or_equal' => $messages['end_after'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Room, 1: CarbonImmutable, 2: CarbonImmutable, 3: int, 4: bool}
     */
    private function resolveBooking(Venue $venue, array $data, string $locale): array
    {
        $messages = self::MESSAGES[$locale];

        $room = $venue->rooms()->where('is_active', true)->where('slug', $data['room_slug'])->first();

        if (! $room) {
            throw ValidationException::withMessages(['room_slug' => $messages['room_unknown']]);
        }

        $startDate = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $endDate = CarbonImmutable::parse($data['end_date'])->startOfDay();

        if ($startDate->diffInDays($endDate) + 1 > ReservationService::MAX_DAYS) {
            throw ValidationException::withMessages([
                'end_date' => str_replace(':max', (string) ReservationService::MAX_DAYS, $messages['too_long']),
            ]);
        }

        $rooms = (int) $data['rooms'];
        $layout = $data['seating_layout'] ?? null;

        if ($layout !== null && ! $room->offersLayout($layout)) {
            $layout = null;
        }

        if (! $this->reservations->fitsCapacity($room, (int) $data['participants'], $rooms, $layout)) {
            throw ValidationException::withMessages(['participants' => $messages['capacity']]);
        }

        return [$room, $startDate, $endDate, $rooms, (bool) ($data['catering'] ?? false)];
    }

    /**
     * The room cannot be booked for those days: say so, and offer the other
     * rooms that can host the same group on the same dates.
     *
     * @param  array<string, mixed>  $data
     */
    private function unavailable(Venue $venue, Room $requested, array $data, string $locale): JsonResponse
    {
        $startDate = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $endDate = CarbonImmutable::parse($data['end_date'])->startOfDay();
        $rooms = (int) $data['rooms'];
        $participants = (int) $data['participants'];
        $layout = $data['seating_layout'] ?? null;

        $alternatives = $venue->rooms()
            ->where('is_active', true)
            ->whereKeyNot($requested->id)
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Room $room) => $this->reservations->fitsCapacity($room, $participants, $rooms, $room->offersLayout((string) $layout) ? $layout : null))
            ->map(function (Room $room) use ($startDate, $endDate, $rooms, $locale) {
                $quote = $this->reservations->quote($room, $startDate, $endDate, $rooms);

                return $quote ? [
                    'slug' => $room->slug,
                    'name' => $room->translatedName($locale),
                    'total' => $quote['grand_total'],
                ] : null;
            })
            ->filter()
            ->values();

        $message = self::MESSAGES[$locale]['unavailable'];

        return response()->json([
            'message' => $message,
            'errors' => ['room_slug' => [$message]],
            'alternatives' => $alternatives,
            'currency' => $venue->currency,
        ], 422);
    }

    private function locale(Request $request, Venue $venue): string
    {
        return in_array($request->input('locale'), self::SUPPORTED_LOCALES, true)
            ? $request->input('locale')
            : $venue->default_locale;
    }

    private function publishedVenue(string $venueSlug): Venue
    {
        $venue = Venue::where('slug', $venueSlug)->first();

        abort_if(! $venue || ! $venue->isPublished(), 404);

        return $venue;
    }
}
