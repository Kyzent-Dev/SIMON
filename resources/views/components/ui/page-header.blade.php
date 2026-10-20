{{-- _shared.md §1 page header: h1 + description + right-aligned actions (primary last). Mobile: actions wrap below, primary full width. Optional slot `meta` sits under the title (tags, key facts). --}}
@props(['title', 'description' => null, 'titleId' => null])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between') }}>
    <div class="min-w-0">
        <h1 @if ($titleId) id="{{ $titleId }}" tabindex="-1" @endif class="font-display text-2xl font-bold text-neutral-900 lg:text-[28px] lg:leading-9">{{ $title }}</h1>
        @if ($description)
            <p class="mt-1 text-sm text-neutral-600">{{ $description }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 max-sm:[&>*:last-child]:w-full sm:shrink-0 sm:justify-end">{{ $actions }}</div>
    @endisset
</div>
