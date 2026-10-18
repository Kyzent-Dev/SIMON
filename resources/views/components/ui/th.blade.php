{{-- Header cell for x-ui.table. `sortable` renders a button (wire:/x-on: attributes go to the button) with aria-sort; `direction` = asc|desc|null. `numeric` right-aligns. --}}
@props(['sortable' => false, 'direction' => null, 'numeric' => false])

@php
    $sort = ['asc' => 'ascending', 'desc' => 'descending'][$direction] ?? 'none';
    $icon = ['asc' => 'chevron-up', 'desc' => 'chevron-down'][$direction] ?? 'chevron-up-down';
    $buttonAttrs = $attributes->filter(fn ($v, $k) => str_starts_with($k, 'wire:') || str_starts_with($k, 'x-on:') || str_starts_with($k, '@'));
@endphp

<th scope="col" @if ($sortable) aria-sort="{{ $sort }}" @endif
    {{ $attributes->except(array_keys($buttonAttrs->getAttributes()))->class([
        'sticky top-0 z-10 h-10 bg-neutral-50 px-4 text-xs font-semibold whitespace-nowrap text-neutral-900 first:z-20',
        'text-right' => $numeric,
    ]) }}>
    @if ($sortable)
        <button type="button" {{ $buttonAttrs }} @class(['-mx-1 inline-flex min-h-11 lg:min-h-0 items-center gap-1 rounded-sm px-1 py-1 hover:text-mint-700', 'flex-row-reverse' => $numeric])>
            {{ $slot }}
            {{ svg('heroicon-m-'.$icon, $direction ? 'size-4' : 'size-4 text-neutral-500') }}
        </button>
    @else
        {{ $slot }}
    @endif
</th>
