{{-- The room directory board: every room with its type, size and day rate, so the guest can step between rooms without going back. --}}
<aside class="room-directory" aria-label="{{ $labels['rooms_heading'] }}">
    <p class="room-directory-title"><span>{{ $labels['rooms_heading'] }}</span><span>{{ $rooms->count() }}</span></p>
    <nav class="room-nav">
        @foreach ($rooms as $item)
            <a href="{{ route('venue.room', ['venueSlug' => $venue->slug, 'roomSlug' => $item->slug, 'lang' => $locale]) }}"
                data-stage-exit
                @if (($room ?? null)?->slug === $item->slug) aria-current="page" @endif>
                @if ($item->images->first())
                    <img class="room-nav-thumb" src="{{ $item->images->first()->image_source }}" alt="" loading="lazy">
                @endif
                <span class="room-nav-text">
                    <span class="room-nav-type">{{ Str::headline($roomTerms['type'][$item->room_type] ?? $item->room_type) }} · {{ $item->capacity }} {{ $lobby['participants_unit'] }}</span>
                    <span class="room-nav-name-row">
                        <span class="room-nav-name">{{ $item->translatedName($locale) }}</span>
                        @if ($item->size_sqm)<span class="room-nav-size">{{ $item->size_sqm }}&nbsp;m²</span>@endif
                    </span>
                    <span class="room-nav-price">{{ $labels['from'] }} {{ $venue->currency }}&nbsp;{{ number_format((float) $item->base_price, 0, ',', '.') }} {{ $labels['per_day'] }}</span>
                </span>
                <span class="room-nav-go" aria-hidden="true">→</span>
            </a>
        @endforeach
    </nav>
</aside>
