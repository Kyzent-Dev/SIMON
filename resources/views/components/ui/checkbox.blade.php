{{-- DESIGN §8 x-ui.checkbox: 20px box, border-strong, checked mint-700 fill + white check (forms plugin uses currentColor). --}}
@props(['label', 'name', 'id' => null, 'value' => '1'])

@php $id ??= $name; @endphp

<label for="{{ $id }}" class="inline-flex min-h-11 cursor-pointer items-center gap-2 text-sm text-neutral-900 lg:min-h-6">
    <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="{{ $value }}" @checked(old($name))
        {{ $attributes->class('size-5 rounded-sm border-border-strong text-mint-700 focus:ring-2 focus:ring-mint-600 focus:ring-offset-2') }}>
    {{ $label }}
</label>
