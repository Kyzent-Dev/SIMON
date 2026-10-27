{{-- Public layout (design/public/landing.md "Header" + "Footer"): skip link, sticky header (56px <768, 64px; shadow-xs after 8px scroll, no
     animation), nav Home · Jadwal · Realisasi, Masuk/Dasbor icon link, hamburger panel below 768 (focus trapped in the header, Esc closes and
     focus returns to the hamburger), footer from the kontak settings. Full page loads (no wire:navigate). --}}
@props(['title' => null])
@php
    $menu = [
        ['label' => 'Home', 'url' => url('/'), 'aktif' => request()->is('/')],
        ['label' => 'Jadwal', 'url' => url('/jadwal'), 'aktif' => request()->is('jadwal')],
        ['label' => 'Realisasi', 'url' => url('/realisasi'), 'aktif' => request()->is('realisasi')],
    ];
    $ikon = 'inline-flex size-11 shrink-0 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900';
@endphp
<!DOCTYPE html>
<html lang="id" class="scroll-pt-14 md:scroll-pt-16">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' · ' : '' }}Inspektorat Daerah Kabupaten Muara Enim</title>
    @vite(['resources/css/app.css', 'resources/js/public.js'])
    @livewireStyles
    {{-- Without JS Alpine never lifts x-cloak: the Jadwal views (data-tampilan) must still show their content. --}}
    <noscript><style>[data-tampilan][x-cloak]{display:block!important}</style></noscript>
</head>
<body class="flex min-h-dvh flex-col bg-neutral-25">
    <a href="#konten" class="sr-only z-[100] rounded-md bg-white px-4 py-2 font-medium text-mint-700 shadow-md focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Lewati ke konten utama</a>

    <header x-data="{ menu: false, gulir: false }" x-init="gulir = scrollY > 8"
        x-on:scroll.window.passive="gulir = scrollY > 8"
        x-on:resize.window="if (innerWidth >= 768) menu = false"
        x-on:keydown.escape="if (menu) { menu = false; $refs.burger.focus() }"
        x-on:click.outside="menu = false"
        x-trap="menu"
        x-bind:class="gulir && 'shadow-xs'"
        class="sticky top-0 z-30 border-b border-neutral-200 bg-white">
        <div class="mx-auto flex h-14 max-w-[1200px] items-center justify-between gap-3 px-4 md:h-16 md:px-6">
            <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3 rounded-md">
                <x-app.logo />
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-semibold text-neutral-900 md:hidden">Inspektorat Muara Enim</span>
                    <span class="hidden text-sm font-semibold text-neutral-900 md:block">Inspektorat Daerah</span>
                    <span class="hidden text-xs text-neutral-600 md:block">Kabupaten Muara Enim</span>
                </span>
            </a>

            <nav aria-label="Navigasi utama" class="hidden md:block">
                <ul role="list" class="flex items-center gap-8">
                    @foreach ($menu as $m)
                        <li>
                            <a href="{{ $m['url'] }}" @if ($m['aktif']) aria-current="page" @endif
                                @class(['inline-flex h-11 items-center rounded-sm text-sm font-medium',
                                    'text-mint-700 underline decoration-mint-700 decoration-2 underline-offset-[6px]' => $m['aktif'],
                                    'text-neutral-600 hover:text-neutral-900' => ! $m['aktif']])>{{ $m['label'] }}</a>
                        </li>
                    @endforeach
                </ul>
            </nav>

            <div class="flex shrink-0 items-center gap-1">
                @auth
                    <a href="{{ route('dashboard') }}" aria-label="Dasbor" title="Dasbor" class="{{ $ikon }}">{{ svg('heroicon-o-squares-2x2', 'size-6', ['aria-hidden' => 'true']) }}</a>
                @else
                    <a href="{{ route('login') }}" aria-label="Masuk" title="Masuk" class="{{ $ikon }}">{{ svg('heroicon-o-arrow-right-on-rectangle', 'size-6', ['aria-hidden' => 'true']) }}</a>
                @endauth
                <button type="button" x-ref="burger" x-on:click="menu = ! menu" aria-controls="menu-publik" aria-expanded="false"
                    x-bind:aria-expanded="menu.toString()" x-bind:aria-label="menu ? 'Tutup menu' : 'Buka menu'" aria-label="Buka menu"
                    class="{{ $ikon }} -mr-2 md:hidden">
                    <span x-show="! menu">{{ svg('heroicon-o-bars-3', 'size-6', ['aria-hidden' => 'true']) }}</span>
                    <span x-show="menu" x-cloak>{{ svg('heroicon-o-x-mark', 'size-6', ['aria-hidden' => 'true']) }}</span>
                </button>
            </div>
        </div>

        <nav id="menu-publik" aria-label="Menu" x-show="menu" x-cloak
            x-transition:enter="transition duration-[var(--dur-base)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:leave="transition duration-[var(--dur-base)] ease-[var(--ease-exit)]" x-transition:leave-end="opacity-0 -translate-y-2"
            class="absolute inset-x-0 top-full border-b border-neutral-200 bg-white shadow-md md:hidden">
            <ul role="list" class="px-2 py-2">
                @foreach ($menu as $m)
                    <li>
                        <a href="{{ $m['url'] }}" @if ($m['aktif']) aria-current="page" @endif
                            @class(['flex h-12 items-center rounded-md px-3 font-medium',
                                'bg-mint-50 text-mint-700' => $m['aktif'],
                                'text-neutral-900 hover:bg-neutral-50' => ! $m['aktif']])>{{ $m['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </header>

    <main id="konten" tabindex="-1" class="flex-1 focus:outline-none">
        {{ $slot }}
    </main>

    @include('partials.footer-publik')

    @livewireScriptConfig
</body>
</html>
