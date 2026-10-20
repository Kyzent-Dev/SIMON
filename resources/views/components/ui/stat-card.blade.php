{{-- DESIGN §8 x-ui.stat-card: default, alert (left 3px danger-700 bar), link (pass `href`). Label caption neutral-600, value in the `stat` style
     (Jakarta 700, 40/44, mobile 32/36, tnum), optional subtext and icon tile (mint-100 bg, mint-800 icon).
     `countUp` runs the §7.3 count-up (Pimpinan/Irban dashboards, first load only): the animated number is aria-hidden and the real value is in
     an sr-only span; reduced motion shows the final value at once (resources/js/countup.js). No hover lift on dashboards. --}}
@props(['label', 'value', 'href' => null, 'variant' => 'default', 'subtext' => null, 'icon' => null, 'countUp' => false])

@php
    $angka = number_format((int) $value, 0, ',', '.');
    $classes = [
        'relative flex min-w-0 flex-col rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5',
        'border-l-[3px] border-l-danger-700' => $variant === 'alert',
        'hover:bg-mint-50' => $href,
    ];
@endphp

<{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class($classes) }}>
    <span class="flex items-start justify-between gap-2">
        <span class="text-xs font-medium text-neutral-600">{{ $label }}</span>
        @if ($icon)
            <span aria-hidden="true" class="shrink-0 rounded-md bg-mint-100 p-1.5">{{ svg('heroicon-o-'.$icon, 'size-5 text-mint-800') }}</span>
        @endif
    </span>
    <span class="mt-auto pt-2 font-display text-[32px] leading-9 font-bold tabular-nums text-neutral-900 lg:text-[40px] lg:leading-[44px]">
        @if ($countUp)
            <span wire:key="nilai-{{ $label }}-{{ $value }}-animasi" aria-hidden="true" x-data="countUp({{ (int) $value }})" x-init="start()" x-text="formatted">{{ $angka }}</span><span class="sr-only">{{ $angka }}</span>
        @else
            <span wire:key="nilai-{{ $label }}-{{ $value }}">{{ $angka }}</span>
        @endif
    </span>
    @if ($subtext)
        <span class="mt-1 text-xs text-neutral-600">{{ $subtext }}</span>
    @endif
</{{ $href ? 'a' : 'div' }}>
