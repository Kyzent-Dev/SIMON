{{-- DESIGN §8 x-app.type-chip: 10px color dot + neutral-900 text, never colored text. The dot is decorative; the name carries the meaning. --}}
@props(['color', 'label'])

<span {{ $attributes->class('inline-flex items-center gap-2 text-sm font-medium text-neutral-900') }}>
    <span aria-hidden="true" class="size-2.5 shrink-0 rounded-full bg-[var(--type)]" style="--type: {{ $color }}"></span>
    {{ $label }}
</span>
