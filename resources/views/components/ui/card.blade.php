{{-- DESIGN §8 x-ui.card: default, interactive (pass `href`, renders a link), mint (mint-50 bg). Radius-lg, border neutral-200, shadow-xs. --}}
@props(['variant' => 'default', 'href' => null])

@php
    $classes = [
        'block rounded-lg border border-neutral-200 p-4 shadow-xs lg:p-5',
        $variant === 'mint' ? 'bg-mint-50' : 'bg-white',
        // card-lift (DESIGN §7.2) for link cards only.
        'transition duration-[var(--dur-fast)] hover:-translate-y-0.5 hover:shadow-md' => $href,
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <div {{ $attributes->class($classes) }}>{{ $slot }}</div>
@endif
