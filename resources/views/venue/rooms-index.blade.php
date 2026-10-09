<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :session="$session" :session-total="$sessionTotal" :backdrop="$backdrop" :menu-items="$menuItems" :title="$labels['rooms_heading'].' · '.$venue->name">
    <x-slot:welcome>
        <div class="stage-welcome stage-welcome-scene">
            <span class="eyebrow">{{ $lobby['session'] }} {{ $session }}</span>
            <h1>{{ $labels['rooms_heading'] }}</h1>
            @if ($roomNarrations['rooms'])
                @include('venue.narrator', ['text' => $roomNarrations['rooms'], 'key' => 'rooms', 'onStage' => true])
            @else
                <p>{{ $lobby['rooms_empty'] }}</p>
            @endif
        </div>
    </x-slot:welcome>

    @if ($rooms->isNotEmpty())
        <x-slot:sidebar>@include('venue.room-nav')</x-slot:sidebar>
    @endif
</x-venue-stage>
