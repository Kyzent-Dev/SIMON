{{-- Compact segmented radio group (irban.md role control): a real <fieldset> + radios, so arrows move between options and the legend names the group
     ("Peran untuk Budi Santoso"). x-ui.radio is a label-beside-dot control and cannot render joined segments. `options` = [value => label];
     wire:model / x-model / data-fokus attributes go on every radio. Checked: white on mint-700 (§3.4). `penuh`: segments share the full width (jadwal.md 360). `icons`: [value => heroicon name] drawn before the label (jadwal.md toggle). --}}
@props(['name', 'legend', 'options' => [], 'value' => null, 'legendVisible' => false, 'penuh' => false, 'icons' => []])

<fieldset {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model', 'data-fokus'])->class('min-w-0') }}>
    <legend @class(['sr-only' => ! $legendVisible, 'mb-1 text-sm font-medium text-neutral-900' => $legendVisible])>{{ $legend }}</legend>
    <div @class(['max-w-full rounded-md border border-border-strong bg-white p-0.5', $penuh ? 'flex w-full' : 'inline-flex'])>
        @foreach ($options as $v => $label)
            <label @class(['relative inline-flex min-h-11 cursor-pointer items-center rounded px-2 text-xs font-medium text-neutral-900 has-checked:bg-mint-700 has-checked:text-white has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-mint-600 lg:min-h-8', 'flex-1 justify-center' => $penuh])>
                <input type="radio" name="{{ $name }}" value="{{ $v }}" @checked((string) $value === (string) $v) class="sr-only"
                    {{ $attributes->whereStartsWith(['wire:model', 'x-model', 'data-fokus']) }}>
                @isset($icons[$v]){{ svg($icons[$v], 'mr-1 size-4 shrink-0', ['aria-hidden' => 'true']) }}@endisset
                <span class="whitespace-nowrap">{{ $label }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
