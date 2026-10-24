{{-- DESIGN §8 x-ui.dropdown: icon trigger (`label` = its accessible name) + menu panel. Opens on click; Esc, outside click or choosing an item closes it.
     The panel is teleported to <body> (z-50, shadow-md) and positioned from the trigger, so a table's overflow-x-auto never clips it.
     x-trap keeps Tab inside the open menu and returns focus to the trigger on close. Items: <button role="menuitem"> in the slot (class via the panel's [role=menuitem] rule).
     Optional slot `trigger` replaces the icon (its `class` styles the button), e.g. the header user menu. --}}
@props(['label', 'icon' => 'ellipsis-vertical'])

<div x-data="{
        open: false, top: 0, left: 0,
        toggle() { this.open ? this.close() : this.show() },
        show() { const r = this.$refs.trigger.getBoundingClientRect(); this.top = r.bottom + 4; this.left = Math.max(8, r.right - 192); this.open = true; this.$nextTick(() => this.$refs.panel.querySelector('[role=menuitem]')?.focus()) },
        close() { this.open = false },
    }" x-on:keydown.escape.window="if (open) { close(); $nextTick(() => $refs.trigger.focus()) }" x-on:resize.window="close()" {{ $attributes->class('inline-flex') }}>
    <button type="button" x-ref="trigger" x-on:click="toggle()" aria-haspopup="menu" x-bind:aria-expanded="open.toString()" aria-expanded="false" aria-label="{{ $label }}"
        class="{{ isset($trigger) ? $trigger->attributes->get('class') : 'inline-flex size-11 items-center justify-center rounded-md border border-border-strong bg-white text-mint-700 hover:bg-mint-50 lg:size-10' }}">
        @isset($trigger) {{ $trigger }} @else {{ svg('heroicon-m-'.$icon, 'size-5') }} @endisset
    </button>
    <template x-teleport="body">
        <div x-show="open" x-cloak x-ref="panel" x-trap="open" role="menu" aria-label="{{ $label }}"
            x-on:click.outside="close()" x-on:click="close()" x-on:scroll.window="close()"
            x-bind:style="`top:${top}px;left:${left}px`"
            x-transition:enter="transition-opacity duration-[var(--dur-fast)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0"
            class="fixed z-50 w-48 rounded-md border border-neutral-200 bg-white py-1 shadow-md
                [&_[role=menuitem]]:flex [&_[role=menuitem]]:min-h-11 [&_[role=menuitem]]:w-full [&_[role=menuitem]]:items-center [&_[role=menuitem]]:gap-2 [&_[role=menuitem]]:px-3 [&_[role=menuitem]]:text-left [&_[role=menuitem]]:text-sm [&_[role=menuitem]]:text-neutral-900 [&_[role=menuitem]]:hover:bg-neutral-50 [&_[role=menuitem]]:focus-visible:bg-neutral-50 lg:[&_[role=menuitem]]:min-h-9">
            {{ $slot }}
        </div>
    </template>
</div>
