@props(['venue', 'locale', 'supportedLocales', 'labels', 'lobby', 'narration', 'scene', 'session', 'sessionTotal', 'backdrop', 'menuItems', 'title' => null, 'welcome' => null, 'sidebar' => null])
{{-- The fixed full-screen stage every session shares: photo, projector screen, session counter, agenda strip and the coordinator chat dock. --}}
@php
    $lobbyUrl = route('venue.show', ['venueSlug' => $venue->slug, 'lang' => $locale]);
    $currentKey = ['room' => 'rooms', 'facility' => 'facilities'][$scene] ?? $scene;
    $currentLabel = collect($menuItems)->firstWhere('key', $currentKey)['label'] ?? $lobby['home'];
    $agenda = [['key' => 'lobby', 'session' => '01', 'label' => $lobby['home'], 'href' => $lobbyUrl, 'tour' => $narration['tour_lobby'], 'topic' => null], ...$menuItems];
    $currentIndex = collect($agenda)->search(fn ($item) => $item['key'] === ($scene === 'lobby' ? 'lobby' : $currentKey));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? $venue->name.' · AI Coordinator' }}</title>
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="stage-page antialiased" style="--coordinator-image: url('{{ $backdrop['avatarImage'] }}'); --stage-focus: {{ $backdrop['focus'] }}; --stage-focus-mobile: {{ $backdrop['focusMobile'] }}; --coordinator-zoom: {{ $backdrop['avatarZoom'] }}; --coordinator-focus: {{ $backdrop['avatarFocus'] }}">
    <main class="stage" data-lobby data-scene="{{ $scene }}" data-page="{{ $scene === 'lobby' ? 'home' : $scene }}" data-session="{{ $session }}" data-chat-dock="right">
        <div class="stage-loader" data-stage-loader role="status">
            <span class="screen-curtain" aria-hidden="true"></span>
            <span class="stage-loader-session" aria-hidden="true">{{ $session }}</span>
            <p>{{ $lobby['loading'] }}</p>
        </div>
        <img src="{{ $backdrop['image'] }}" alt="" class="stage-image" fetchpriority="high">
        <div class="stage-shade"></div>
        @if ($scene !== 'room')
            <img src="{{ $backdrop['character'] }}" alt="{{ $lobby['assistant'] }}" class="stage-character" data-pose="{{ $backdrop['pose'] }}">
        @endif
        <p class="stage-tour" data-stage-tour aria-live="polite"></p>

        <header class="stage-header">
            <a href="{{ $lobbyUrl }}" @if($scene !== 'lobby') data-stage-exit data-tour-line="{{ $narration['tour_lobby'] }}" @endif class="stage-brand">
                <x-venue-mark />
                <span class="min-w-0"><span class="stage-brand-name">{{ $venue->name }}</span><span class="stage-brand-city">{{ $venue->city }} · {{ $venue->country }}</span></span>
            </a>
            <div class="session-counter" aria-live="polite">
                <span class="session-counter-word">{{ $lobby['session'] }}</span>
                <span class="session-counter-code" data-session-code>{{ $session }}</span>
                <span class="session-counter-total">/ {{ str_pad((string) $sessionTotal, 2, '0', STR_PAD_LEFT) }}</span>
                <span class="session-counter-label">{{ $currentLabel }}</span>
            </div>
            <div class="stage-tools">
                <x-sound-toggle :on="$lobby['sound_on']" :off="$lobby['sound_off']" />
                <nav aria-label="Language" class="stage-lang">
                    @foreach ($supportedLocales as $code)
                        <a href="?lang={{ $code }}" lang="{{ $code }}" aria-label="{{ ['id' => 'Bahasa Indonesia', 'en' => 'English', 'ja' => '日本語'][$code] }}" @if($code === $locale) aria-current="true" @endif>{{ $code }}</a>
                    @endforeach
                </nav>
            </div>
        </header>

        {{-- The agenda strip: the six sessions of the programme, in order, like a timetable pinned under the header. --}}
        <nav class="agenda" aria-label="{{ $lobby['explore'] }}">
            <p class="agenda-title">{{ $lobby['agenda'] }}</p>
            <ol class="agenda-list">
                @foreach ($agenda as $item)
                    @php $isCurrent = $loop->index === $currentIndex; @endphp
                    <li>
                        <a href="{{ $item['href'] }}" class="agenda-item @if($loop->index < $currentIndex) is-done @endif"
                            data-stage-exit @if($item['tour'] && ! $isCurrent) data-tour-line="{{ $item['tour'] }}" @endif @if($item['topic']) data-topic="{{ $item['topic'] }}" @endif
                            @if($isCurrent) aria-current="page" @endif>
                            <span class="agenda-num" aria-hidden="true">{{ $item['session'] }}</span>
                            <span class="agenda-label">{{ $item['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>
            <span class="agenda-footer"><span class="status-dot" aria-hidden="true"></span>{{ $lobby['available'] }}</span>
        </nav>

        {{ $welcome }}

        {{ $sidebar }}

        {{ $slot }}

        <div data-chat-widget>
            @include('venue.chat')
        </div>
        <noscript><p class="stage-noscript">Aktifkan JavaScript untuk menggunakan navigasi dan AI Coordinator. {{ $venue->phone }}</p></noscript>
    </main>
</body>
</html>
