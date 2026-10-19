{{-- DESIGN §8 x-ui.button. `busy` is an Alpine expression: while true the spinner replaces the leading icon. `disabledWhen` (Alpine, defaults to `busy`) disables the button. --}}
@props(['variant' => 'primary', 'size' => 'md', 'type' => 'submit', 'icon' => null, 'busy' => null, 'disabledWhen' => null])

@php $disabledWhen ??= $busy; @endphp

@php
    $variants = [
        'primary' => 'bg-mint-700 text-white enabled:hover:bg-mint-800',
        'secondary' => 'bg-white text-mint-700 border border-border-strong enabled:hover:bg-mint-50',
        'ghost' => 'bg-transparent text-mint-700 enabled:hover:bg-mint-50',
        'danger' => 'bg-danger-700 text-white',
        'danger-outline' => 'bg-white text-danger-700 border border-danger-700 enabled:hover:bg-danger-700 enabled:hover:text-white',
        'brand-soft' => 'bg-mint-300 text-mint-950',
    ];
    $sizes = ['sm' => 'h-8 px-3 text-sm', 'md' => 'h-11 lg:h-10 px-4 text-sm', 'lg' => 'h-12 px-5 text-base'];
@endphp

<button type="{{ $type }}"
    @if ($disabledWhen) x-bind:disabled="{{ $disabledWhen }}" x-bind:aria-disabled="({{ $disabledWhen }}).toString()" @endif
    {{ $attributes->class([
        'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors duration-[var(--dur-fast)]',
        'enabled:active:translate-y-px enabled:active:duration-[var(--dur-instant)]',
        'disabled:cursor-not-allowed disabled:border-transparent disabled:bg-neutral-100 disabled:text-neutral-400',
        $variants[$variant], $sizes[$size],
    ]) }}>
    @if ($busy)
        <svg x-show="{{ $busy }}" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3" />
            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" />
        </svg>
    @endif
    @if ($icon)
        <span @if ($busy) x-show="!({{ $busy }})" @endif class="inline-flex">{{ svg('heroicon-m-'.$icon, 'size-5') }}</span>
    @endif
    {{ $slot }}
</button>
