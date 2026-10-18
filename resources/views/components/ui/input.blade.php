{{-- DESIGN §8 x-ui.input: label above, helper, error with icon linked by aria-describedby, "*" + sr-only "(wajib)". Slot `suffix` sits inside the field (e.g. a show/hide button). --}}
@props(['label', 'name', 'id' => null, 'type' => 'text', 'value' => null, 'required' => false, 'error' => null, 'helper' => null])

@php
    $id ??= $name;
    $describedBy = trim(implode(' ', array_filter([
        $attributes->get('aria-describedby'),
        $helper ? $id.'-helper' : null,
        $error ? $id.'-error' : null,
    ])));
@endphp

<div>
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-neutral-900">
        {{ $label }}@if ($required)<span class="text-danger-700" aria-hidden="true"> *</span><span class="sr-only"> (wajib)</span>@endif
    </label>
    <div class="relative">
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @required($required)
            @if ($error) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except('aria-describedby')->class([
                'block h-12 w-full rounded-md bg-white px-3 text-base text-neutral-900 lg:h-10 lg:text-sm',
                'focus:border-mint-700 focus:ring-2 focus:ring-mint-600',
                'border-danger-700' => $error,
                'border-border-strong' => ! $error,
                'pr-12' => isset($suffix),
            ]) }}>
        {{ $suffix ?? '' }}
    </div>
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
