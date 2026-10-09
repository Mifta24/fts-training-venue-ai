@php
    $roomName = $room->translatedName($locale);
    $money = fn ($value) => $venue->currency."\u{00A0}".number_format((float) $value, 0, ',', '.');
    $typeLabel = Str::headline($roomTerms['type'][$room->room_type] ?? $room->room_type);
    $programRate = $room->base_price * $programDays * (100 - $venue->programDiscountPercent($programDays)) / 100;
    $primaryImage = $room->images->first();
    $roomUrl = fn ($item) => route('venue.room', ['venueSlug' => $venue->slug, 'roomSlug' => $item->slug, 'lang' => $locale]);
    $layoutMax = max(collect($room->seating_layouts ?? [])->max('capacity') ?? 0, $room->capacity, 1);
@endphp
<section class="sheet @container" aria-label="{{ $roomName }}">
    <a href="{{ route('venue.rooms', ['venueSlug' => $venue->slug, 'lang' => $locale]) }}" data-stage-exit class="panel-close" aria-label="{{ $labels['rooms_heading'] }}"><span aria-hidden="true">×</span></a>
    <article class="room-scene">
        <div class="flex flex-wrap items-center justify-between gap-3 pr-12">
            <p class="eyebrow">{{ $lobby['room_counter'] }} {{ str_pad((string) ($roomIndex + 1), 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $rooms->count(), 2, '0', STR_PAD_LEFT) }}</p>
            @if ($rooms->count() > 1)
                <nav class="flex gap-2" aria-label="{{ $lobby['room_scene'] }}">
                    <a href="{{ $roomUrl($previousRoom) }}" data-stage-exit class="room-scene-step" rel="prev"><span aria-hidden="true">←</span> {{ $lobby['prev_room'] }}</a>
                    <a href="{{ $roomUrl($nextRoom) }}" data-stage-exit class="room-scene-step" rel="next">{{ $lobby['next_room'] }} <span aria-hidden="true">→</span></a>
                </nav>
            @endif
        </div>

        <div class="mt-4 grid gap-7 @2xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div data-room-gallery>
                <div class="room-scene-photo">
                    @if ($primaryImage)
                        <img src="{{ $primaryImage->image_source }}" alt="{{ $primaryImage->alt_text }}" data-room-gallery-main>
                    @else
                        <div class="grid h-full place-items-center text-sm">{{ $lobby['no_photo'] }}</div>
                    @endif
                    <span class="room-scene-badge">{{ $typeLabel }}</span>
                </div>
                @if ($room->images->count() > 1)
                    <ul class="mt-2 grid grid-cols-4 gap-2" aria-label="{{ $lobby['gallery'] }}">
                        @foreach ($room->images as $imageIndex => $image)
                            <li>
                                <button type="button" class="room-scene-thumb" data-room-thumb data-src="{{ $image->image_source }}" data-alt="{{ $image->alt_text }}" aria-label="{{ $lobby['photo'] }} {{ $imageIndex + 1 }}" @if($imageIndex === 0) aria-current="true" @endif>
                                    <img src="{{ $image->image_source }}" alt="" class="h-full w-full object-cover" loading="lazy">
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @include('venue.narrator', ['text' => $roomNarrations['room'][$room->slug], 'key' => 'room-'.$room->slug])
            </div>

            <div class="min-w-0">
                <h2 class="mt-0!">{{ $roomName }}</h2>
                <p class="room-scene-description">{{ $room->translatedDescription($locale) }}</p>

                <div class="room-scene-rate">
                    <p><span class="room-scene-rate-label">{{ $labels['from'] }}</span> <span class="room-scene-rate-amount"><strong>{{ $money($room->base_price) }}</strong> <span class="room-scene-rate-label">{{ $labels['per_day'] }}</span></span></p>
                    @if ($venue->multiday_discount_percent > 0 || $venue->intensive_discount_percent > 0)
                        <p class="room-scene-program">{{ str_replace([':days', ':price'], [(string) $programDays, $money($programRate)], $lobby['program_estimate']) }}</p>
                    @endif
                    <p class="room-scene-note">{{ $lobby['availability_note'] }}</p>
                </div>

                <div class="mt-5 flex flex-wrap gap-3">
                    <a href="{{ route('venue.reservation', ['venueSlug' => $venue->slug, 'lang' => $locale, 'room' => $room->slug]) }}" data-stage-exit data-tour-line="{{ $narration['tour_reservation'] }}" class="btn">{{ $lobby['reserve_room'] }} <span aria-hidden="true">↗</span></a>
                    <button type="button" class="room-scene-step" data-hero-quick-message="{{ str_replace(':name', $roomName, $lobby['ask_room_q']) }}">{{ $lobby['ask_room'] }}</button>
                </div>

                <dl class="room-scene-facts">
                    <div><dt>{{ $lobby['type'] }}</dt><dd>{{ $typeLabel }}</dd></div>
                    <div><dt>{{ $lobby['capacity'] }}</dt><dd>{{ $room->capacity }} {{ $lobby['participants_unit'] }}</dd></div>
                    @if ($room->size_sqm)
                        <div><dt>{{ $lobby['size'] }}</dt><dd>{{ $room->size_sqm }} m²</dd></div>
                    @endif
                    @if ($room->floor_label)
                        <div><dt>{{ $lobby['floor'] }}</dt><dd>{{ $room->floor_label }}</dd></div>
                    @endif
                    <div>
                        <dt>{{ $lobby['catering'] }}</dt>
                        <dd>
                            @if ($room->catering_included)
                                {{ $lobby['catering_included'] }}
                            @elseif ($room->catering_available)
                                {{ $lobby['catering_yes'] }}@if ($room->catering_price > 0) (+{{ $money($room->catering_price) }} {{ $labels['per_day'] }})@endif
                            @else
                                {{ $lobby['catering_no'] }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if (! empty($room->seating_layouts))
                    <h3 class="room-scene-subheading">{{ $lobby['layouts'] }}</h3>
                    <div class="layout-bars">
                        @foreach ($room->seating_layouts as $layout)
                            <div class="layout-bar">
                                <span>{{ $roomTerms['layout'][$layout['type']] ?? Str::headline($layout['type']) }}</span>
                                <span class="layout-bar-track"><span class="layout-bar-fill" style="width: {{ round($layout['capacity'] / $layoutMax * 100) }}%"></span></span>
                                <span class="layout-bar-value">{{ $layout['capacity'] }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (! empty($room->amenities))
                    <h3 class="room-scene-subheading">{{ $lobby['amenities'] }}</h3>
                    <ul class="room-scene-chips">
                        @foreach ($room->amenities as $amenity)
                            <li>{{ $roomTerms['amenity'][$amenity] ?? Str::headline($amenity) }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </article>
</section>
