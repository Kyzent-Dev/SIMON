{{-- DESIGN §8 x-ui.drawer from the right: sm 400 / md 560 / lg 720 (default; create/edit forms, admin.md Layout).
     Same open/close contract as x-ui.modal: `wire:model` (x-modelable) or the `open-modal` / `close-modal` window events with {name}.
     Slot `footer` is the sticky action bar. --}}
@props(['name', 'title', 'size' => 'lg'])

@php $width = ['sm' => 'max-w-[400px]', 'md' => 'max-w-[560px]', 'lg' => 'max-w-[720px]'][$size]; @endphp

<div x-data="{ open: false }" x-modelable="open" {{ $attributes->whereStartsWith('wire:model') }}
    x-on:open-modal.window="if ($event.detail?.name === @js($name)) open = true"
    x-on:close-modal.window="if ($event.detail?.name === @js($name)) open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70]" x-on:keydown.escape.prevent.stop="open = false">
            <div x-show="open" x-on:click="open = false" aria-hidden="true" class="absolute inset-0 bg-scrim"
                x-transition:enter="transition-opacity duration-[var(--dur-base)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0"
                x-transition:leave="transition-opacity duration-[var(--dur-fast)] ease-[var(--ease-exit)]" x-transition:leave-end="opacity-0"></div>
            <div x-show="open" x-trap.noscroll.inert="open" role="dialog" aria-modal="true" aria-labelledby="{{ $name }}-title"
                x-transition:enter="transition duration-[var(--dur-base)] ease-[var(--ease-enter)]" x-transition:enter-start="translate-x-full"
                x-transition:leave="transition duration-[var(--dur-fast)] ease-[var(--ease-exit)]" x-transition:leave-end="translate-x-full"
                {{ $attributes->whereDoesntStartWith('wire:model')->class(['absolute inset-y-0 right-0 flex w-full flex-col bg-white shadow-lg', $width]) }}>
                <div class="flex h-16 shrink-0 items-center justify-between gap-4 border-b border-neutral-200 px-4 lg:px-5">
                    <h2 id="{{ $name }}-title" class="truncate font-display text-[22px] leading-[30px] font-bold text-neutral-900">{{ $title }}</h2>
                    <button type="button" x-on:click="open = false" aria-label="Tutup"
                        class="-mr-2 inline-flex size-11 shrink-0 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900">
                        {{ svg('heroicon-o-x-mark', 'size-6') }}
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto px-4 py-5 text-sm text-neutral-900 lg:px-5">{{ $slot }}</div>
                @isset($footer)
                    <div class="flex shrink-0 flex-wrap justify-end gap-2 border-t border-neutral-200 bg-white px-4 py-3 lg:px-5">{{ $footer }}</div>
                @endisset
            </div>
        </div>
    </template>
</div>
