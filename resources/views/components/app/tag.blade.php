{{-- Rectangular tag (`rounded`, 4px) for PKPT and revision states: leading 14px icon + 12px/600 label. Pills stay reserved for SPT status (DESIGN §3.6).
     Variants use §3.4 pairs only: mint (mint-800 on mint-100), neutral (neutral-900 on neutral-100), warning (accent-700 on white), danger (danger-700 on white). --}}
@props(['icon', 'variant' => 'neutral'])

@php
    $variants = [
        'mint' => 'border-mint-200 bg-mint-100 text-mint-800',
        'neutral' => 'border-neutral-300 bg-neutral-100 text-neutral-900',
        'warning' => 'border-accent-400 bg-white text-accent-700',
        'danger' => 'border-danger-700 bg-white text-danger-700',
    ];
@endphp

<span {{ $attributes->class(['inline-flex h-6 items-center gap-1 whitespace-nowrap rounded border px-2 text-xs font-semibold', $variants[$variant]]) }}>
    {{ svg('heroicon-m-'.$icon, 'size-3.5 shrink-0') }}{{ $slot }}
</span>
