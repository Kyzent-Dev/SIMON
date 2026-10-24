{{-- Spec 7.2: the user's photo (rounded-full, decorative alt: the name is always next to it) or initials on mint-100/mint-800 (_shared.md §1). --}}
@props(['user'])

@if ($url = $user->fotoUrl())
    <img src="{{ $url }}" alt="" {{ $attributes->class('shrink-0 rounded-full object-cover') }}>
@else
    <span aria-hidden="true" {{ $attributes->class('flex shrink-0 items-center justify-center rounded-full bg-mint-100 font-semibold text-mint-800') }}>{{ $user->initials() }}</span>
@endif
