{{-- The brand mark: a whiteboard on an easel with a tick on it, drawn rather than lettered so it reads at any size. --}}
<span {{ $attributes->merge(['class' => 'venue-mark']) }} aria-hidden="true">
    <svg viewBox="0 0 32 32" width="24" height="24" fill="none">
        <rect x="5" y="5" width="22" height="15" rx="2.2" stroke="currentColor" stroke-width="2"/>
        <path d="m10.5 12.8 3.2 3.2 6.3-6.6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M16 20v3.5M10.5 28l5.5-4.5 5.5 4.5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
</span>
