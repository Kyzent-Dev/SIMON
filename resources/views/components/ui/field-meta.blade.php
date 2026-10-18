{{-- Shared label/helper/error markup for x-ui.select and x-ui.textarea (same rules as x-ui.input, DESIGN §8). --}}
@props(['part', 'id', 'label' => null, 'required' => false, 'helper' => null, 'error' => null])

@if ($part === 'label')
    <label for="{{ $id }}" class="mb-1.5 block text-sm font-medium text-neutral-900">
        {{ $label }}@if ($required)<span class="text-danger-700" aria-hidden="true"> *</span><span class="sr-only"> (wajib)</span>@endif
    </label>
@else
    @if ($helper)
        <p id="{{ $id }}-helper" class="mt-1.5 text-xs text-neutral-500">{{ $helper }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
            {{ svg('heroicon-m-exclamation-circle', 'size-5 shrink-0') }}
            <span>{{ $error }}</span>
        </p>
    @endif
@endif
