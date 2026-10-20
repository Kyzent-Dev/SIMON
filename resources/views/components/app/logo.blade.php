{{-- Provisional mark (DESIGN §2: no official vector in the repo yet; replace with the Diskominfo file). Shield with the "Delapan Sungai" waves,
     mint-700 on light surfaces, accent-400 on dark ones. Decorative: the text beside it carries the name. --}}
@props(['gelap' => false])

<svg viewBox="0 0 36 36" fill="none" aria-hidden="true" focusable="false" {{ $attributes->class(['size-9 shrink-0', $gelap ? 'text-accent-400' : 'text-mint-700']) }}>
    <path d="M18 2 31 7v10c0 8.2-5.4 14.6-13 17-7.6-2.4-13-8.8-13-17V7l13-5Z" fill="currentColor" />
    <g stroke="{{ $gelap ? 'currentColor' : 'white' }}" stroke-width="1.6" stroke-linecap="round" @class(['text-mint-950' => $gelap])>
        <path d="M10 14c2.7-1.6 5.3 1.6 8 0s5.3 1.6 8 0" />
        <path d="M10 19c2.7-1.6 5.3 1.6 8 0s5.3 1.6 8 0" />
        <path d="M12 24c2-1.2 4 1.2 6 0s4 1.2 6 0" />
    </g>
</svg>
