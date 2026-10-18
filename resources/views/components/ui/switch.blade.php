{{-- DESIGN §8 x-ui.switch: native checkbox with role="switch" (works with wire:model and plain forms). Off: white track, border-strong edge and thumb;
     on: mint-700 track, white thumb moved right, so state is shown by position, not color alone. No thumb animation (forms, DESIGN §7.5). --}}
@props(['label', 'name', 'id' => null, 'value' => '1', 'checked' => false, 'description' => null])

@php $id ??= $name; @endphp

<div class="flex min-h-11 items-center gap-3 lg:min-h-6">
    <span class="relative inline-flex shrink-0">
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox" role="switch" value="{{ $value }}" @checked(old($name, $checked))
            @if ($description) aria-describedby="{{ $id }}-description" @endif
            {{ $attributes->class('peer absolute inset-0 size-full cursor-pointer opacity-0') }}>
        <span aria-hidden="true" class="pointer-events-none flex h-6 w-11 items-center rounded-full border border-border-strong bg-white p-0.5 peer-checked:border-mint-700 peer-checked:bg-mint-700 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-mint-600 peer-checked:[&>span]:translate-x-5 peer-checked:[&>span]:bg-white">
            <span class="size-[18px] rounded-full bg-border-strong"></span>
        </span>
    </span>
    <span class="min-w-0">
        <label for="{{ $id }}" class="cursor-pointer text-sm font-medium text-neutral-900">{{ $label }}</label>
        @if ($description)
            <span id="{{ $id }}-description" class="block text-xs text-neutral-500">{{ $description }}</span>
        @endif
    </span>
</div>
