{{-- DESIGN §8 x-ui.tabs: role=tablist/tab, roving tabindex, ArrowLeft/Right/Home/End move focus and activate (automatic activation).
     Server-driven: clicking sets the Livewire property `model` to the tab key. Put the content in an element with role="tabpanel" id="{{ $panel }}" aria-labelledby="{{ $panel }}-tab-{active}". Underline is the only motion (--dur-fast).
     `badges` (optional): [key => [count, accessible text]] adds a neutral counter after the label when count > 0. --}}
@props(['tabs', 'active', 'model', 'label', 'panel', 'badges' => []])

<div role="tablist" aria-label="{{ $label }}" class="mb-4 flex gap-1 overflow-x-auto border-b border-neutral-200"
    x-data x-on:keydown="const t = [...$el.querySelectorAll('[role=tab]')]; const i = t.indexOf($event.target); const n = { ArrowRight: (i + 1) % t.length, ArrowLeft: (i - 1 + t.length) % t.length, Home: 0, End: t.length - 1 }[$event.key]; if (i < 0 || n === undefined) return; $event.preventDefault(); t[n].focus(); t[n].click()">
    @foreach ($tabs as $key => $text)
        <button type="button" role="tab" id="{{ $panel }}-tab-{{ $key }}" aria-selected="{{ $key === $active ? 'true' : 'false' }}" aria-controls="{{ $panel }}"
            tabindex="{{ $key === $active ? '0' : '-1' }}" wire:click="$set('{{ $model }}', '{{ $key }}')"
            @class([
                'inline-flex min-h-11 shrink-0 items-center border-b-2 px-4 text-sm font-medium transition-colors duration-[var(--dur-fast)] -mb-px lg:min-h-10',
                'border-mint-700 text-mint-700' => $key === $active,
                'border-transparent text-neutral-600 hover:text-neutral-900' => $key !== $active,
            ])>{{ $text }}@if (($badges[$key][0] ?? 0) > 0)<span aria-hidden="true" class="ml-2 inline-flex min-w-5 items-center justify-center rounded border border-neutral-300 bg-neutral-100 px-1.5 text-xs font-semibold text-neutral-900 tabular-nums">{{ $badges[$key][0] }}</span><span class="sr-only">, {{ $badges[$key][1] }}</span>@endif</button>
    @endforeach
</div>
