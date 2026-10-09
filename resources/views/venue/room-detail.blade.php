<x-venue-stage :venue="$venue" :locale="$locale" :supported-locales="$supportedLocales" :labels="$labels" :lobby="$lobby" :narration="$narration" :scene="$scene" :session="$session" :session-total="$sessionTotal" :backdrop="$backdrop" :menu-items="$menuItems" :title="$room->translatedName($locale).' · '.$venue->name">
    @if($rooms->isNotEmpty())
        <x-slot:sidebar>@include('venue.room-nav')</x-slot:sidebar>
    @endif

    <div class="stage-panels">
        @include('venue.room-scene')
    </div>
</x-venue-stage>
