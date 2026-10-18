{{-- DESIGN §8 x-ui.modal: sm 400 / md 560 / lg 720. Open with `wire:model="showX"` on the component (x-modelable),
     or `$dispatch('open-modal', {name})` / Livewire `$this->dispatch('open-modal', name: '…')`; close with `close-modal`.
     x-trap.noscroll.inert traps focus and returns it to the trigger; Esc closes. Slot `footer` holds the buttons ("Batal" + primary/danger). --}}
@props(['name', 'title', 'size' => 'md'])

@php $width = ['sm' => 'max-w-[400px]', 'md' => 'max-w-[560px]', 'lg' => 'max-w-[720px]'][$size]; @endphp

<div x-data="{ open: false }" x-modelable="open" {{ $attributes->whereStartsWith('wire:model') }}
    x-on:open-modal.window="if ($event.detail?.name === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail?.name === @js($name)) open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center p-4" x-on:keydown.escape.prevent.stop="open = false">
            <div x-show="open" x-on:click="open = false" aria-hidden="true" class="absolute inset-0 bg-scrim"
                x-transition:enter="transition-opacity duration-[var(--dur-base)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity duration-[var(--dur-fast)] ease-[var(--ease-exit)]" x-transition:leave-end="opacity-0"></div>
            <div x-show="open" x-trap.noscroll.inert="open" role="dialog" aria-modal="true" aria-labelledby="{{ $name }}-title"
                x-transition:enter="transition duration-[var(--dur-base)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0 scale-[.97]"
                x-transition:leave="transition duration-[var(--dur-fast)] ease-[var(--ease-exit)]" x-transition:leave-end="opacity-0 scale-[.97]"
                {{ $attributes->whereDoesntStartWith('wire:model')->class(['relative flex max-h-[calc(100dvh-2rem)] w-full flex-col rounded-lg bg-white shadow-lg', $width]) }}>
                <div class="flex items-start justify-between gap-4 px-5 pt-5">
                    <h2 id="{{ $name }}-title" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">{{ $title }}</h2>
                    <button type="button" x-on:click="open = false" aria-label="Tutup"
                        class="-m-1 inline-flex size-10 shrink-0 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900">
                        {{ svg('heroicon-o-x-mark', 'size-6') }}
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-4 text-sm text-neutral-900">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex flex-wrap justify-end gap-2 border-t border-neutral-200 px-5 py-4">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
