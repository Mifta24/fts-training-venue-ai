<?php

namespace App\Services\Coordinator;

use App\Models\Conversation;
use App\Models\HandoverRequest;
use App\Models\Room;
use App\Models\Venue;
use App\Models\VenueKnowledgeItem;
use App\Notifications\NewHandoverRequest;
use App\Services\Reservation\ReservationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Executes the AI Coordinator's tools against this venue's controlled data.
 * Every venue fact, room, price and availability answer must pass through
 * here — the model itself is never trusted to hold any of it.
 */
class VenueCoordinatorTools
{
    public function __construct(
        private readonly Venue $venue,
        private readonly Conversation $conversation,
        private readonly string $locale,
        private readonly ReservationService $reservations = new ReservationService,
    ) {}

    /**
     * OpenAI-compatible function-calling schema (used by the local LM Studio
     * / Ollama endpoint via CoordinatorService — see topic in that class).
     */
    public static function definitions(): array
    {
        return array_map(
            fn (array $tool) => ['type' => 'function', 'function' => $tool],
            [
                [
                    'name' => 'search_knowledge',
                    'description' => 'Search the training venue\'s approved knowledge base: house rules and policies, equipment and facilities (projector, sound system, whiteboards, Wi-Fi, breakout areas), catering, nearby accommodation, transport and parking, how a training day runs, and FAQs. Always use this instead of answering venue-fact questions from memory. Returns up to 5 matching entries.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'query' => ['type' => 'string', 'description' => 'Keywords from the guest question, e.g. "projector", "lunch" or "parking"'],
                            'category' => [
                                'type' => 'string',
                                'enum' => ['general', 'facilities', 'policies', 'catering', 'transport', 'faq'],
                                'description' => 'Optional category filter',
                            ],
                        ],
                        'required' => ['query'],
                    ],
                ],
                [
                    'name' => 'search_rooms',
                    'description' => 'Search the venue\'s training rooms (classroom, workshop lab, boardroom, hall, creative studio) that fit the guest\'s dates and group size, with real-time availability and the total price — multi-day discount already applied — checked. Use this whenever a guest describes what they want (dates, participants, set-up, room type, budget) rather than naming one room by name.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'start_date' => ['type' => 'string', 'description' => 'First day of the programme, YYYY-MM-DD'],
                            'end_date' => ['type' => 'string', 'description' => 'Last day of the programme, YYYY-MM-DD (the same as start_date for a one-day event)'],
                            'participants' => ['type' => 'integer', 'minimum' => 1],
                            'seating_layout' => ['type' => 'string', 'enum' => Room::SEATING_LAYOUTS, 'description' => 'Optional preferred set-up: theatre, classroom, u_shape, boardroom, banquet or cluster'],
                            'room_type' => ['type' => 'string', 'enum' => Room::TYPES, 'description' => 'Optional room type preference'],
                            'catering' => ['type' => 'boolean', 'description' => 'Whether the guest wants lunch and coffee breaks added to the price'],
                        ],
                        'required' => ['start_date', 'end_date', 'participants'],
                    ],
                ],
                [
                    'name' => 'get_room_detail',
                    'description' => 'Get full details and photos for one room by its slug (from a previous search_rooms result): size, floor, capacity in each seating layout, equipment, catering options and the multi-day discounts. Use this when the guest asks to see more about, or see photos of, a specific room.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_slug' => ['type' => 'string'],
                            'image_tag' => ['type' => 'string', 'description' => 'Optional filter, e.g. "classroom", "stage", "projector", "catering", "outdoor"'],
                        ],
                        'required' => ['room_slug'],
                    ],
                ],
                [
                    'name' => 'check_availability',
                    'description' => 'Get the current, real-time availability and exact total price for one room over specific days, including optional catering and any multi-day or intensive discount. ALWAYS call this before confirming a price or telling a guest a room is available — never state a price or availability from memory.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_slug' => ['type' => 'string'],
                            'start_date' => ['type' => 'string'],
                            'end_date' => ['type' => 'string'],
                            'participants' => ['type' => 'integer', 'description' => 'Needed to price catering'],
                            'rooms' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of rooms, default 1'],
                            'catering' => ['type' => 'boolean'],
                        ],
                        'required' => ['room_slug', 'start_date', 'end_date'],
                    ],
                ],
                [
                    'name' => 'create_booking_request',
                    'description' => 'Create a booking request after the guest confirms the room and dates and you have their name and phone. This re-checks availability before booking. It does not charge payment or sign a contract — it creates a pending request for the venue team to confirm.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'room_slug' => ['type' => 'string'],
                            'start_date' => ['type' => 'string'],
                            'end_date' => ['type' => 'string'],
                            'participants' => ['type' => 'integer'],
                            'rooms' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Number of rooms, default 1'],
                            'seating_layout' => ['type' => 'string', 'enum' => Room::SEATING_LAYOUTS],
                            'catering' => ['type' => 'boolean'],
                            'organization' => ['type' => 'string', 'description' => 'Company or institution organising the training'],
                            'event_title' => ['type' => 'string', 'description' => 'Name or topic of the programme'],
                            'guest_name' => ['type' => 'string'],
                            'guest_email' => ['type' => 'string'],
                            'guest_phone' => ['type' => 'string'],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['room_slug', 'start_date', 'end_date', 'participants', 'guest_name', 'guest_phone'],
                    ],
                ],
                [
                    'name' => 'request_human_handover',
                    'description' => 'Hand this conversation over to a human member of the venue team. Use this for special requests, complaints, corporate or group programmes, negotiated rates, programmes longer than the online limit, unusual cancellations, payment problems, or any question you cannot answer confidently from the available tools. Always write a clear summary so staff do not need to ask the guest to repeat themselves.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'reason' => [
                                'type' => 'string',
                                'enum' => ['special_request', 'complaint', 'group_booking', 'negotiated_rate', 'unusual_cancellation', 'payment_issue', 'low_confidence'],
                            ],
                            'summary' => ['type' => 'string', 'description' => 'What the guest wants and the relevant context gathered so far, written for a staff member who has not seen this conversation'],
                        ],
                        'required' => ['reason', 'summary'],
                    ],
                ],
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{text: string, ui: array|null}
     */
    public function dispatch(string $name, array $input): array
    {
        return match ($name) {
            'search_knowledge' => $this->searchKnowledge($input),
            'search_rooms' => $this->searchRooms($input),
            'get_room_detail' => $this->getRoomDetail($input),
            'check_availability' => $this->checkAvailability($input),
            'create_booking_request' => $this->createBookingRequest($input),
            'request_human_handover' => $this->requestHumanHandover($input),
            default => ['text' => "Unknown tool: {$name}", 'ui' => null],
        };
    }

    private function searchKnowledge(array $input): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        $category = $input['category'] ?? null;
        $words = collect(preg_split('/\s+/', Str::lower($query)))->filter();

        $items = $this->venue->knowledgeItems()
            ->where('is_active', true)
            ->when($category, fn ($q) => $q->where('category', $category))
            ->get()
            ->filter(function (VenueKnowledgeItem $item) use ($query, $words) {
                if ($query === '') {
                    return true;
                }

                $haystack = Str::lower($item->title.' '.$item->body.' '.implode(' ', $item->tags ?? []));

                return Str::contains($haystack, Str::lower($query))
                    || $words->contains(fn ($word) => Str::contains($haystack, $word));
            })
            ->take(5);

        if ($items->isEmpty()) {
            return [
                'text' => 'No matching knowledge base entry was found. Do not guess the answer — tell the guest you will check with the team, or call request_human_handover.',
                'ui' => null,
            ];
        }

        $results = $items->map(fn (VenueKnowledgeItem $item) => [
            'category' => $item->category,
            'title' => $item->translatedTitle($this->locale),
            'body' => $item->translatedBody($this->locale),
        ])->values()->all();

        return ['text' => json_encode($results, JSON_UNESCAPED_UNICODE), 'ui' => null];
    }

    private function searchRooms(array $input): array
    {
        [$startDate, $endDate, $days] = $this->parseProgram($input['start_date'], $input['end_date']);
        $participants = (int) $input['participants'];
        $layout = $input['seating_layout'] ?? null;
        $roomType = $input['room_type'] ?? null;
        $catering = (bool) ($input['catering'] ?? false);

        if ($days < 1) {
            return ['text' => 'end_date cannot be before start_date.', 'ui' => null];
        }

        $candidates = $this->venue->rooms()
            ->where('is_active', true)
            ->with('images')
            ->get()
            ->filter(fn (Room $room) => $this->reservations->fitsCapacity($room, $participants, 1, $layout && $room->offersLayout($layout) ? $layout : null))
            ->when($layout, fn ($c) => $c->filter(fn (Room $room) => $room->offersLayout($layout)))
            ->when($roomType, fn ($c) => $c->filter(fn (Room $room) => $room->room_type === $roomType));

        $matches = [];
        foreach ($candidates as $room) {
            $quote = $this->reservations->quote($room, $startDate, $endDate, 1, $catering, $participants);
            if (! $quote) {
                continue;
            }

            $thumbnail = $room->images->first();

            $matches[] = [
                'room_slug' => $room->slug,
                'name' => $room->translatedName($this->locale),
                'room_type' => $room->room_type,
                'size_sqm' => $room->size_sqm,
                'floor_label' => $room->floor_label,
                'capacity' => $room->capacity,
                'seating_layouts' => $room->seating_layouts,
                'catering_included' => $room->catering_included,
                'days' => $days,
                'discount_percent' => $quote['discount_percent'],
                'total_price' => $quote['grand_total'],
                'currency' => $this->venue->currency,
                'min_available_rooms' => $quote['min_available_rooms'],
                'thumbnail_url' => $thumbnail?->image_source,
            ];
        }

        if (empty($matches)) {
            return [
                'text' => 'No room is free on every day of that programme for this group size. Tell the guest honestly and offer to check different dates or a split into two rooms, or call request_human_handover if they want to be put on a waiting list.',
                'ui' => null,
            ];
        }

        usort($matches, fn ($a, $b) => $a['total_price'] <=> $b['total_price']);

        return [
            'text' => json_encode($matches, JSON_UNESCAPED_UNICODE),
            'ui' => [
                'type' => 'room_results',
                'start_date' => $input['start_date'],
                'end_date' => $input['end_date'],
                'rooms' => $matches,
            ],
        ];
    }

    private function getRoomDetail(array $input): array
    {
        $room = $this->findRoom($input['room_slug']);
        if (! $room) {
            return ['text' => 'Room not found.', 'ui' => null];
        }

        $images = $room->images;
        $tag = $input['image_tag'] ?? null;
        if ($tag) {
            $images = $images->filter(fn ($img) => $img->hasTag($tag));
        }

        $detail = [
            'room_slug' => $room->slug,
            'name' => $room->translatedName($this->locale),
            'description' => $room->translatedDescription($this->locale),
            'room_type' => $room->room_type,
            'size_sqm' => $room->size_sqm,
            'floor_label' => $room->floor_label,
            'capacity' => $room->capacity,
            'seating_layouts' => $room->seating_layouts,
            'catering_included' => $room->catering_included,
            'catering_available' => $room->catering_available,
            'catering_price_per_participant_per_day' => $room->catering_price,
            'amenities' => $room->amenities,
            'starting_price' => $room->base_price,
            'currency' => $this->venue->currency,
            'program_discounts' => [
                'multiday_discount_percent' => $this->venue->multiday_discount_percent,
                'multiday_from_days' => Venue::MULTIDAY_MIN_DAYS,
                'intensive_discount_percent' => $this->venue->intensive_discount_percent,
                'intensive_from_days' => Venue::INTENSIVE_MIN_DAYS,
            ],
            'note' => 'starting_price is a per-day room rate before catering and programme discounts and is indicative only — always call check_availability for the exact price on real dates.',
            'images' => $images->map(fn ($img) => [
                'url' => $img->image_source,
                'tags' => $img->tags,
                'alt' => $img->alt_text,
            ])->values()->all(),
        ];

        return [
            'text' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'room_detail', 'room' => $detail],
        ];
    }

    private function checkAvailability(array $input): array
    {
        $room = $this->findRoom($input['room_slug']);
        if (! $room) {
            return ['text' => 'Room not found.', 'ui' => null];
        }

        [$startDate, $endDate, $days] = $this->parseProgram($input['start_date'], $input['end_date']);
        if ($days < 1) {
            return ['text' => 'end_date cannot be before start_date.', 'ui' => null];
        }

        $rooms = max(1, (int) ($input['rooms'] ?? 1));
        $catering = (bool) ($input['catering'] ?? false);
        $quote = $this->reservations->quote($room, $startDate, $endDate, $rooms, $catering, (int) ($input['participants'] ?? 0));

        if (! $quote) {
            return [
                'text' => json_encode([
                    'available' => false,
                    'room_slug' => $room->slug,
                    'message' => 'Not every day in this range has availability for this room.',
                ], JSON_UNESCAPED_UNICODE),
                'ui' => ['type' => 'availability', 'available' => false, 'room_slug' => $room->slug],
            ];
        }

        $result = [
            'available' => true,
            'room_slug' => $room->slug,
            'name' => $room->translatedName($this->locale),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'daily_breakdown' => $quote['daily'],
            'room_total' => $quote['room_total'],
            'catering_total' => $quote['catering_total'],
            'subtotal' => $quote['subtotal'],
            'discount_percent' => $quote['discount_percent'],
            'discount_total' => $quote['discount_total'],
            'grand_total' => $quote['grand_total'],
            'currency' => $this->venue->currency,
            'min_available_rooms' => $quote['min_available_rooms'],
        ];

        return [
            'text' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'availability', 'available' => true, 'quote' => $result],
        ];
    }

    private function createBookingRequest(array $input): array
    {
        $room = $this->findRoom($input['room_slug']);
        if (! $room) {
            return ['text' => 'Room not found.', 'ui' => null];
        }

        [$startDate, $endDate, $days] = $this->parseProgram($input['start_date'], $input['end_date']);
        if ($days < 1) {
            return ['text' => 'end_date cannot be before start_date.', 'ui' => null];
        }

        if ($days > ReservationService::MAX_DAYS) {
            return ['text' => 'This programme is longer than the online limit of '.ReservationService::MAX_DAYS.' days. Call request_human_handover so the sales team can prepare a quote.', 'ui' => null];
        }

        $rooms = max(1, (int) ($input['rooms'] ?? 1));
        $layout = $input['seating_layout'] ?? null;

        if (! $this->reservations->fitsCapacity($room, (int) $input['participants'], $rooms, $layout && $room->offersLayout($layout) ? $layout : null)) {
            return ['text' => 'This group does not fit that room for the requested number of rooms. Suggest a larger room or more rooms.', 'ui' => null];
        }

        $booking = $this->reservations->createRequest($this->venue, $room, [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'participants' => (int) $input['participants'],
            'rooms' => $rooms,
            'seating_layout' => $layout,
            'catering' => (bool) ($input['catering'] ?? false),
            'guest_name' => $input['guest_name'],
            'guest_email' => $input['guest_email'] ?? null,
            'guest_phone' => $input['guest_phone'],
            'locale' => $this->locale,
            'organization' => $input['organization'] ?? null,
            'event_title' => $input['event_title'] ?? null,
            'notes' => $input['notes'] ?? null,
        ], $this->conversation);

        if (! $booking) {
            return [
                'text' => 'The room is no longer available for these exact days — availability may have just changed. Call check_availability again or offer alternative dates.',
                'ui' => null,
            ];
        }

        $payload = [
            'booking_reference' => $booking->reference,
            'room_slug' => $room->slug,
            'name' => $room->translatedName($this->locale),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days' => $days,
            'rooms' => $rooms,
            'participants' => $booking->participants,
            'total_price' => (float) $booking->total_price,
            'currency' => $this->venue->currency,
            'status' => 'pending_confirmation',
        ];

        return [
            'text' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'booking_confirmation', 'booking' => $payload],
        ];
    }

    private function requestHumanHandover(array $input): array
    {
        $reason = $input['reason'];
        $summary = $input['summary'];

        $handover = HandoverRequest::create([
            'conversation_id' => $this->conversation->id,
            'reason' => $reason,
            'summary' => $summary,
            'status' => HandoverRequest::STATUS_OPEN,
        ]);

        $this->venue->notifyStaff(new NewHandoverRequest($handover));

        $this->conversation->update([
            'status' => Conversation::STATUS_HANDED_OVER,
            'handover_summary' => $summary,
        ]);

        return [
            'text' => json_encode([
                'ok' => true,
                'message' => 'A staff member has been notified and will join this conversation shortly.',
            ], JSON_UNESCAPED_UNICODE),
            'ui' => ['type' => 'handover', 'reason' => $reason, 'summary' => $summary],
        ];
    }

    // --- helpers ---

    private function findRoom(string $slug): ?Room
    {
        return $this->venue->rooms()->where('slug', $slug)->first();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: int}
     */
    private function parseProgram(string $startDate, string $endDate): array
    {
        $start = CarbonImmutable::parse($startDate)->startOfDay();
        $end = CarbonImmutable::parse($endDate)->startOfDay();

        return [$start, $end, (int) $start->diffInDays($end) + 1];
    }
}
