{{-- DESIGN §8 x-ui.stat-card: default, alert (left 3px danger-700 bar), link (pass `href`), band (no own border/shadow: a cell of the public
     stats band, landing.md). Label caption neutral-600, value in the `stat` style (Jakarta 700, 40/44, mobile 32/36, tnum), optional subtext,
     icon tile (mint-100 bg, mint-800 icon) and slot (e.g. x-ui.progress). `teks` shows a ready string verbatim (e.g. `12 baris`, `Belum ada`). `desimal`/`akhiran` format e.g. `73,8%`; a null value shows `—`.
     `countUp` runs the §7.3 count-up once the card scrolls into view: the animated number is aria-hidden and the real value is in an sr-only
     span; reduced motion shows the final value at once (resources/js/countup.js). No hover lift on dashboards. --}}
@props(['label', 'value' => null, 'href' => null, 'variant' => 'default', 'subtext' => null, 'icon' => null, 'countUp' => false, 'desimal' => 0, 'akhiran' => '', 'teks' => null])

@php
    $angka = $teks ?? ($value === null ? '—' : number_format((float) $value, $desimal, ',', '.').$akhiran);
    $classes = [
        'relative flex min-w-0 flex-col bg-white p-4 lg:p-5',
        'rounded-lg border border-neutral-200 shadow-xs' => $variant !== 'band',
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
    <span @class(['pt-2 font-display text-[32px] leading-9 font-bold tabular-nums text-neutral-900 lg:text-[40px] lg:leading-[44px]', 'mt-auto' => $variant !== 'band'])>
        @if ($countUp && $value !== null)
            <span aria-hidden="true"><span wire:key="nilai-{{ $label }}-{{ $value }}-animasi" x-data="countUp({{ $value + 0 }}, {{ (int) $desimal }})" x-intersect.once="start()" x-text="formatted">{{ number_format((float) $value, $desimal, ',', '.') }}</span>{{ $akhiran }}</span><span class="sr-only">{{ $angka }}</span>
        @else
            <span wire:key="nilai-{{ $label }}-{{ $value }}">{{ $angka }}</span>
        @endif
    </span>
    @if ($subtext)
        <span class="mt-1 text-xs text-neutral-600">{{ $subtext }}</span>
    @endif
    {{ $slot }}
</{{ $href ? 'a' : 'div' }}>
