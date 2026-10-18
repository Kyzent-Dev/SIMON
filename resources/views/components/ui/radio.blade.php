{{-- DESIGN §8 x-ui.radio: 20px, border-strong, checked mint-700 fill. Group them in a <fieldset> with a <legend>. --}}
@props(['label', 'name', 'value', 'id' => null, 'checked' => false])

@php $id ??= $name.'-'.$value; @endphp

<label for="{{ $id }}" class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-neutral-900 lg:min-h-6">
    <input id="{{ $id }}" name="{{ $name }}" type="radio" value="{{ $value }}" @checked(old($name) !== null ? (string) old($name) === (string) $value : $checked)
        {{ $attributes->class('size-5 border-border-strong text-mint-700 focus:ring-2 focus:ring-mint-600 focus:ring-offset-2') }}>
    {{ $label }}
</label>
