@props(['name' => 'star'])
<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes }}>
    @switch($name)
        @case('projector')
            <rect x="3" y="8" width="18" height="8" rx="2"/><circle cx="9" cy="12" r="2.2"/><path d="M15 11h3M15 13.5h3M6 16v2.5M18 16v2.5"/>
            @break
        @case('sound')
            <path d="M4 9.5v5h3.5L12 18.5v-13L7.5 9.5H4Z"/><path d="M15.5 9a4 4 0 0 1 0 6M18 6.5a7.5 7.5 0 0 1 0 11"/>
            @break
        @case('whiteboard')
            <rect x="3" y="4" width="18" height="12" rx="1.5"/><path d="M7 8.5h5M7 11.5h8M9 16l-2 4.5M15 16l2 4.5"/>
            @break
        @case('wifi')
            <path d="M2.5 8.5a14 14 0 0 1 19 0M5.5 12a9.5 9.5 0 0 1 13 0M8.5 15.5a5 5 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor"/>
            @break
        @case('dining')
            <path d="M5 11h12v3.5a5 5 0 0 1-5 5h-2a5 5 0 0 1-5-5V11Z"/><path d="M17 12.5h1.5a2.2 2.2 0 0 1 0 4.4H16.5M8 3.5c-1 1.2 1 2 0 3.5M12 3.5c-1 1.2 1 2 0 3.5"/>
            @break
        @case('stay')
            <path d="M3.5 19V6M3.5 15h17v4M20.5 15v-3.5a2.5 2.5 0 0 0-2.5-2.5h-7v6"/><circle cx="7.2" cy="11" r="1.6"/>
            @break
        @case('parking')
            <path d="M5 17h14M5 17v-5l2-5h10l2 5v5M5 12h14"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/>
            @break
        @case('transit')
            <rect x="5.5" y="3" width="13" height="14" rx="3"/><path d="M5.5 10h13M9 13.5h.01M15 13.5h.01M8 21l2-4M16 21l-2-4"/>
            @break
        @case('transport')
            <path d="M10.5 13.5 3 11l1-2 8 1 4-5a1.5 1.5 0 0 1 2 2l-5 4 1 8-2 1-2.5-7.5L6 15.5V18l-1.5 1-1-3-3-1 1-1.5h2.5Z"/>
            @break
        @case('support')
            <path d="M4.5 13v-1.5a7.5 7.5 0 0 1 15 0V13"/><rect x="3.5" y="12.5" width="4" height="6" rx="1.6"/><rect x="16.5" y="12.5" width="4" height="6" rx="1.6"/><path d="M18.5 18.5c0 1.6-1.6 2.5-4 2.5"/>
            @break
        @case('work')
            <rect x="3" y="5" width="18" height="11" rx="1.2"/><path d="M8 20h8M12 16v4"/>
            @break
        @case('security')
            <path d="M12 3.5 19 6v5.5c0 4.6-3 8.2-7 9.3-4-1.1-7-4.7-7-9.3V6l7-2.5Z"/><path d="M12 9v3.5M12 15.5h.01"/>
            @break
        @case('place')
            <path d="M12 21s-6.5-5.4-6.5-11a6.5 6.5 0 0 1 13 0c0 5.6-6.5 11-6.5 11Z"/><circle cx="12" cy="10" r="2.3"/>
            @break
        @default
            <path d="m12 3.5 2.6 5.3 5.9.9-4.3 4.1 1 5.8L12 16.8 6.8 19.6l1-5.8-4.3-4.1 5.9-.9L12 3.5Z"/>
    @endswitch
</svg>
