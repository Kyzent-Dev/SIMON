{{-- DESIGN §8 x-ui.table: comfortable (48px rows) / compact (36px). Sticky neutral-50 header, row hover neutral-50,
     selected rows (`data-selected` on <tr>) mint-50 + 3px mint-700 left edge, tabular-nums everywhere, numeric cells: add `text-right`.
     Scrolls inside its own keyboard-focusable region with a right fade hint; first column sticky while scrolling sideways.
     The ≥1024 max height lets the header stick inside the scroll container. No animation (DESIGN §7.5). --}}
@props(['label', 'density' => 'comfortable'])

<div x-data="{ more: false, check() { this.more = this.$refs.box.scrollLeft + this.$refs.box.clientWidth < this.$refs.box.scrollWidth - 1 } }"
    x-init="check()" x-on:resize.window="check()" {{ $attributes->class('relative rounded-lg border border-neutral-200 bg-white shadow-xs') }}>
    <div x-ref="box" x-on:scroll="check()" role="region" aria-label="{{ $label }}" tabindex="0"
        class="overflow-x-auto rounded-lg lg:max-h-[calc(100dvh-14rem)] lg:overflow-y-auto">
        <table @class([
            'w-full border-collapse text-left text-sm tabular-nums text-neutral-900',
            '[&_td]:h-12' => $density === 'comfortable',
            '[&_td]:h-9' => $density === 'compact',
            '[&_td]:px-4 [&_td]:py-1 [&_tbody_tr]:border-t [&_tbody_tr]:border-neutral-200 [&_tbody_tr]:bg-white [&_tbody_tr:hover]:bg-neutral-50',
            '[&_tbody_tr[data-selected]]:bg-mint-50 [&_tbody_tr[data-selected]>td:first-child]:shadow-[inset_3px_0_0_var(--color-mint-700)]',
            '[&_tr>:first-child]:sticky [&_tr>:first-child]:left-0 [&_td:first-child]:bg-inherit',
        ])>
            <caption class="sr-only">{{ $label }}</caption>
            <thead>
                <tr>{{ $head }}</tr>
            </thead>
            <tbody>
                {{ $slot }}
            </tbody>
        </table>
    </div>
    <div x-show="more" x-cloak aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 w-8 rounded-r-lg bg-linear-to-l from-white to-transparent"></div>
</div>
