{{-- DESIGN §8 x-ui.progress (bar): mint-700 fill on neutral-100, the label always visible as text (the bar itself is aria-hidden; the text carries
     the value). The fill is clamped to 0–100% (more SPT than planned shows a full bar). Shared by the public Home and Jadwal. --}}
@props(['nilai', 'maks', 'label' => null])

@php
    $persen = $maks > 0 ? round(min(100, max(0, $nilai / $maks * 100)), 1) : 0;
@endphp

<div {{ $attributes->class('min-w-0') }}>
    <div class="h-2 overflow-hidden rounded-full bg-neutral-100" aria-hidden="true">
        <div class="h-full rounded-full bg-mint-700" style="width: {{ $persen }}%"></div>
    </div>
    <p class="mt-1.5 text-xs text-neutral-600 tabular-nums">{{ $label ?? "{$nilai} dari {$maks} SPT selesai" }}</p>
</div>
