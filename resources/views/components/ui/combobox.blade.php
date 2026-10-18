{{-- DESIGN §8 x-ui.combobox: Alpine listbox (role=combobox, aria-expanded, aria-activedescendant, aria-controls). Single or `multiple` (selected items become removable chips).
     Search is CLIENT-SIDE: the full `options` ([value => label]) are passed in and filtered in the browser (fine for a few hundred pegawai; for large sets switch to a
     Livewire query debounced 250ms). "Tidak ditemukan" shows when nothing matches. Bind with wire:model (x-modelable): an int/null (single) or a list of ints (multiple).
     Keys: ArrowDown/Up move (and open), Enter picks, Esc closes, Backspace on empty search removes the last chip. --}}
@props(['name', 'label', 'options' => [], 'multiple' => false, 'id' => null, 'placeholder' => 'Cari…', 'error' => null, 'helper' => null])

@php
    $id ??= $name;
    $list = collect($options)->map(fn ($l, $v) => ['value' => (string) $v, 'label' => (string) $l])->values();
    $describedBy = trim(implode(' ', array_filter([$helper ? $id.'-helper' : null, $error ? $id.'-error' : null])));
@endphp

<div x-data="{
        multiple: @js((bool) $multiple), options: @js($list), value: @js($multiple ? [] : null), open: false, q: '', active: 0,
        get selected() { return this.multiple ? (this.value ?? []).map(String) : (this.value == null || this.value === '' ? [] : [String(this.value)]) },
        get filtered() { const t = this.q.trim().toLowerCase(); return this.options.filter((o) => t === '' || o.label.toLowerCase().includes(t)) },
        label(v) { return this.options.find((o) => o.value === String(v))?.label ?? '' },
        isSel(o) { return this.selected.includes(o.value) },
        show() { this.open = true },
        hide() { this.open = false; this.q = ''; this.active = 0 },
        move(d) { if (!this.open) { this.show(); return } const n = this.filtered.length; if (n) this.active = (this.active + d + n) % n },
        pick(o) {
            if (!o) return;
            const v = Number(o.value);
            if (this.multiple) { this.value = this.isSel(o) ? this.value.filter((x) => String(x) !== o.value) : [...(this.value ?? []), v]; this.q = ''; this.$refs.input.focus() }
            else { this.value = v; this.hide() }
        },
        remove(v) { this.value = this.value.filter((x) => String(x) !== String(v)) },
        clear() { this.value = null; this.$refs.input.focus() },
        back() { if (this.multiple && this.q === '' && this.value?.length) this.value = this.value.slice(0, -1) },
    }"
    x-modelable="value" {{ $attributes->whereStartsWith('wire:model') }}
    x-on:click.outside="hide()" x-on:keydown.escape="if (open) { $event.stopPropagation(); hide() }"
    x-init="$watch('q', () => active = 0)"
    class="relative">
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-neutral-900">{{ $label }}</label>

    <div class="flex min-h-12 w-full flex-wrap items-center gap-1.5 rounded-md border bg-white px-2 py-1.5 focus-within:border-mint-700 focus-within:ring-2 focus-within:ring-mint-600 lg:min-h-10 {{ $error ? 'border-danger-700' : 'border-border-strong' }}">
        @if ($multiple)
            <template x-for="v in selected" :key="v">
                <span class="inline-flex max-w-full items-center gap-1 rounded-md border border-neutral-200 bg-neutral-100 py-0.5 pr-0.5 pl-2 text-sm text-neutral-900">
                    <span class="truncate" x-text="label(v)"></span>
                    <button type="button" x-on:click="remove(v)" x-bind:aria-label="'Hapus ' + label(v)" class="inline-flex size-8 shrink-0 items-center justify-center rounded text-neutral-600 hover:bg-neutral-200 hover:text-neutral-900 lg:size-6">
                        {{ svg('heroicon-m-x-mark', 'size-4') }}
                    </button>
                </span>
            </template>
        @endif
        <input id="{{ $id }}" x-ref="input" type="text" role="combobox" autocomplete="off" placeholder="{{ $placeholder }}"
            aria-autocomplete="list" aria-haspopup="listbox" aria-controls="{{ $id }}-listbox"
            aria-expanded="false" x-bind:aria-expanded="open.toString()"
            aria-activedescendant="" x-bind:aria-activedescendant="open && filtered[active] ? '{{ $id }}-opt-' + filtered[active].value : ''"
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            @if ($error) aria-invalid="true" @endif
            x-bind:value="open || multiple ? q : label(value)"
            x-on:input="q = $event.target.value; show()"
            x-on:focus="show()" x-on:click="show()"
            x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
            x-on:keydown.enter="if (open) { $event.preventDefault(); pick(filtered[active]) }"
            x-on:keydown.backspace="back()" x-on:keydown.tab="hide()"
            class="h-9 min-w-24 flex-1 border-0 bg-transparent p-1 text-base text-neutral-900 placeholder:text-neutral-500 focus:ring-0 lg:text-sm">
        @unless ($multiple)
            <button type="button" x-show="selected.length" x-cloak x-on:click="clear()" aria-label="Kosongkan pilihan" class="inline-flex size-9 shrink-0 items-center justify-center rounded text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900">
                {{ svg('heroicon-m-x-mark', 'size-5') }}
            </button>
        @endunless
    </div>

    <ul id="{{ $id }}-listbox" role="listbox" aria-label="{{ $label }}" @if ($multiple) aria-multiselectable="true" @endif x-show="open" x-cloak
        class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-md border border-neutral-200 bg-white py-1 shadow-md">
        <template x-for="(o, i) in filtered" :key="o.value">
            <li role="option" x-bind:id="'{{ $id }}-opt-' + o.value" x-bind:aria-selected="isSel(o).toString()" aria-selected="false"
                x-on:mousedown.prevent="pick(o)" x-on:mousemove="active = i"
                x-bind:class="i === active ? 'bg-neutral-100' : ''"
                class="flex min-h-11 cursor-pointer items-center gap-2 px-3 text-sm text-neutral-900 lg:min-h-9">
                <span class="size-5 shrink-0">
                    <span x-show="isSel(o)" x-cloak>{{ svg('heroicon-m-check', 'size-5 text-mint-700') }}</span>
                </span>
                <span x-text="o.label"></span>
            </li>
        </template>
        <li x-show="filtered.length === 0" x-cloak role="presentation" class="px-3 py-2 text-sm text-neutral-600">Tidak ditemukan</li>
    </ul>

    <p class="sr-only" aria-live="polite" x-text="open ? filtered.length + ' pilihan tersedia' : ''"></p>
    @if ($helper)
        <p id="{{ $id }}-helper" class="mt-1.5 text-xs text-neutral-500">{{ $helper }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
            {{ svg('heroicon-m-exclamation-circle', 'size-5 shrink-0') }}
            <span>{{ $error }}</span>
        </p>
    @endif
</div>
