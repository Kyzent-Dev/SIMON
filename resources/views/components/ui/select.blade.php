{{-- DESIGN §8 x-ui.select. `options` = [value => label]; `placeholder` adds an empty first option. Extra <option>s may go in the slot. --}}
@props(['label', 'name', 'id' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'required' => false, 'error' => null, 'helper' => null])

@php
    $id ??= $name;
    $selected = (string) old($name, $value);
    $describedBy = trim(implode(' ', array_filter([$helper ? $id.'-helper' : null, $error ? $id.'-error' : null])));
@endphp

<div>
    <x-ui.field-meta part="label" :id="$id" :label="$label" :required="$required" />
    <select id="{{ $id }}" name="{{ $name }}" @required($required)
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block h-12 w-full rounded-md bg-white pr-10 pl-3 text-base text-neutral-900 lg:h-10 lg:text-sm',
            'focus:border-mint-700 focus:ring-2 focus:ring-mint-600',
            $error ? 'border-danger-700' : 'border-border-strong',
        ]) }}>
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
    <x-ui.field-meta part="meta" :id="$id" :helper="$helper" :error="$error" />
</div>
