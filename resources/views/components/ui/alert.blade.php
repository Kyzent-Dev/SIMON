{{-- DESIGN §8 x-ui.alert: inline banner, icon + (title) + body. White surface keeps every text pair inside §3.4; the colored edge and icon are decoration, meaning is in the text. --}}
@props(['variant' => 'info', 'title' => null, 'icon' => null])

@php
    $styles = [
        'info' => ['border-l-info-800', 'text-info-800', 'information-circle'],
        'warning' => ['border-l-accent-700', 'text-accent-700', 'exclamation-triangle'],
        'danger' => ['border-l-danger-700', 'text-danger-700', 'x-circle'],
        'success' => ['border-l-success-700', 'text-success-700', 'check-circle'],
    ];
    [$edge, $iconColor, $defaultIcon] = $styles[$variant];
@endphp

<div {{ $attributes->class(['flex gap-3 rounded-md border border-l-4 border-neutral-200 bg-white p-3 text-sm text-neutral-900', $edge]) }}>
    {{ svg('heroicon-m-'.($icon ?? $defaultIcon), 'mt-px size-5 shrink-0 '.$iconColor) }}
    <div class="min-w-0">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        {{ $slot }}
    </div>
</div>
