@php
    $money = fn ($value) => $venue->currency.' '.number_format((float) $value, 0, ',', '.');
    $roomsItem = collect($menuItems)->firstWhere('key', 'rooms');
    $reservationItem = collect($menuItems)->firstWhere('key', 'reservation');
    $maxDiscount = max($venue->multiday_discount_percent, $venue->intensive_discount_percent);
@endphp
<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :session="$session" :session-total="$sessionTotal" :backdrop="$backdrop" :menu-items="$menuItems">
    <x-slot:welcome>
        <div class="stage-welcome">
            <span class="eyebrow">{{ $venue->name }} · {{ $venue->city }}</span>
            <h1>{{ $lobby['welcome'] }} <em>{{ $lobby['lobby'] }}.</em></h1>
            <p>{{ $lobby['intro'] }}</p>

            @if ($rooms->isNotEmpty())
                <div class="lobby-cta">
                    <a href="{{ $roomsItem['href'] }}" class="btn btn-marker" data-stage-exit data-tour-line="{{ $roomsItem['tour'] }}" data-topic="{{ $roomsItem['topic'] }}">{{ $lobby['cta_rooms'] }} <span aria-hidden="true">→</span></a>
                    <a href="{{ $reservationItem['href'] }}" class="btn-ghost" data-stage-exit data-tour-line="{{ $reservationItem['tour'] }}" data-topic="{{ $reservationItem['topic'] }}">{{ $lobby['start_booking'] }}</a>
                </div>
            @endif

            <dl class="lobby-stats">
                @if ($rooms->isNotEmpty())
                    <div><dt>{{ $lobby['stat_from'] }}</dt><dd>{{ $money($rooms->min('base_price')) }}</dd></div>
                @endif
                @if ($maxDiscount > 0)
                    <div><dt>{{ $lobby['stat_program'] }}</dt><dd>{{ str_replace(':percent', (string) $maxDiscount, $lobby['stat_program_value']) }}</dd></div>
                @endif
                @if ($venue->opening_time && $venue->closing_time)
                    <div><dt>{{ $lobby['stat_hours'] }}</dt><dd>{{ substr($venue->opening_time, 0, 5) }}–{{ substr($venue->closing_time, 0, 5) }}</dd></div>
                @endif
            </dl>
        </div>
    </x-slot:welcome>
</x-venue-stage>
