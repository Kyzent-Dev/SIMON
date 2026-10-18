{{-- DESIGN §8 x-ui.empty-state: 48px line icon mint-600 (graphic, 4.02:1), title, one sentence, actions in the slot. --}}
@props(['icon' => 'inbox', 'title', 'description' => null])

<div {{ $attributes->class('flex flex-col items-center px-4 py-12 text-center') }}>
    {{ svg('heroicon-o-'.$icon, 'size-12 text-mint-600') }}
    <p class="mt-4 text-base font-semibold text-neutral-900">{{ $title }}</p>
    @if ($description)
        <p class="mt-1 max-w-md text-sm text-neutral-600">{{ $description }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-6 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
