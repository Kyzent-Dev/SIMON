{{-- DESIGN §8 x-ui.card: default, interactive (pass `href`, renders a link with a static hover color), mint (mint-50 bg). Radius-lg, border neutral-200.
     `lift` opts into card-lift (DESIGN §7.2) for public link cards only; never on dashboards (admin.md Motion). --}}
@props(['variant' => 'default', 'href' => null, 'lift' => false])

@php
    $classes = [
        'block border border-neutral-200 p-4 lg:p-5',
        $lift ? 'rounded-xl' : 'rounded-lg', // DESIGN §5: radius-xl for landing cards
        $variant === 'mint' ? 'bg-mint-50' : 'bg-white',
        'hover:border-mint-700' => $href && $variant === 'mint',
        'hover:bg-mint-50' => $href && $variant !== 'mint',
        'shadow-xs' => ! $lift,
        'shadow-sm hover:shadow-md transition duration-[var(--dur-fast)] hover:-translate-y-0.5' => $lift,
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <div {{ $attributes->class($classes) }}>{{ $slot }}</div>
@endif
