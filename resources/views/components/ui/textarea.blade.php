{{-- DESIGN §8 x-ui.textarea: same label/helper/error rules as x-ui.input. --}}
@props(['label', 'name', 'id' => null, 'value' => null, 'rows' => 4, 'required' => false, 'error' => null, 'helper' => null])

@php
    $id ??= $name;
    $describedBy = trim(implode(' ', array_filter([$helper ? $id.'-helper' : null, $error ? $id.'-error' : null])));
@endphp

<div>
    <x-ui.field-meta part="label" :id="$id" :label="$label" :required="$required" />
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
        @if ($error) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->class([
            'block w-full rounded-md bg-white px-3 py-2 text-base text-neutral-900 lg:text-sm',
            'focus:border-mint-700 focus:ring-2 focus:ring-mint-600',
            $error ? 'border-danger-700' : 'border-border-strong',
        ]) }}>{{ old($name, $value) }}</textarea>
    <x-ui.field-meta part="meta" :id="$id" :helper="$helper" :error="$error" />
</div>
