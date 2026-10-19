@props(['title' => null, 'breadcrumbs' => []])
@php
    $user = auth()->user();
    $groups = \App\Support\Navigation::groups($user->role);
    // Collapsed (≥1024): the label turns into a tooltip next to the icon, so the link keeps its accessible name.
    $tip = "collapsed && 'lg:absolute lg:left-full lg:z-[90] lg:ml-3 lg:rounded-md lg:bg-mint-950 lg:px-2 lg:py-1 lg:text-xs lg:text-white lg:opacity-0 lg:pointer-events-none lg:group-hover:opacity-100 lg:group-focus-visible:opacity-100'";
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-neutral-25 lg:flex">
    <a href="#konten" class="sr-only z-[100] rounded-md bg-white px-4 py-2 font-medium text-mint-700 shadow-md focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Lewati ke konten utama</a>

    {{-- _shared.md §1 sidebar: 264/72px ≥1024, off-canvas drawer from the left below 1024. --}}
    @persist('sidebar')
    <div x-data="{ collapsed: $persist(false).as('sidebar-ciut') }" class="lg:shrink-0">
        <div x-show="$store.nav.open" x-cloak x-on:click="$store.nav.open = false" aria-hidden="true"
            x-transition:enter="transition-opacity duration-[var(--dur-base)] ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0"
            x-transition:leave="transition-opacity duration-[var(--dur-fast)] ease-[var(--ease-exit)]" x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[60] bg-scrim lg:hidden"></div>

        <aside id="sidebar"
            x-trap.noscroll="$store.nav.open"
            x-on:keydown.escape="$store.nav.open = false"
            x-bind:role="$store.nav.open ? 'dialog' : null"
            x-bind:aria-modal="$store.nav.open ? 'true' : null"
            aria-label="Menu"
            x-bind:class="{ 'max-lg:invisible max-lg:-translate-x-full': ! $store.nav.open, 'lg:w-[72px]': collapsed, 'lg:w-[264px]': ! collapsed }"
            class="fixed inset-y-0 left-0 z-[60] flex w-[264px] max-w-[85vw] flex-col border-r border-neutral-200 bg-white shadow-lg transition-[translate,width,visibility] duration-[var(--dur-base)] ease-[var(--ease-standard)] max-lg:invisible max-lg:-translate-x-full lg:sticky lg:top-0 lg:z-40 lg:h-dvh lg:w-[264px] lg:shadow-none">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-neutral-200 px-4">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-md bg-mint-700 font-display text-lg font-bold text-white" aria-hidden="true">S</span>
                <p class="min-w-0 flex-1 truncate font-display text-lg font-bold text-mint-700" x-bind:class="collapsed && 'lg:sr-only'">SIMON-SPT</p>
                <button type="button" x-on:click="$store.nav.open = false" aria-label="Tutup menu"
                    class="-mr-2 inline-flex size-11 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 lg:hidden">
                    {{ svg('heroicon-o-x-mark', 'size-6') }}
                </button>
            </div>

            <nav aria-label="Navigasi utama" class="flex-1 overflow-y-auto px-3 py-4" x-bind:class="collapsed && 'lg:overflow-visible'">
                @foreach ($groups as $group)
                    @if ($group['label'])
                        <p class="px-3 pt-4 pb-1 text-xs font-semibold uppercase tracking-[0.08em] text-neutral-500" x-bind:class="collapsed && 'lg:sr-only'">{{ $group['label'] }}</p>
                    @endif
                    <ul role="list" class="space-y-1">
                        @foreach ($group['items'] as $item)
                            @php
                                // A parent route with its own child item (irban.spt vs irban.spt.ajukan) matches exactly, so only one item is current.
                                $exact = collect($groups)->pluck('items')->flatten(1)->contains(fn ($o) => str_starts_with($o['route'], $item['route'].'.'));
                                $active = $exact ? request()->routeIs($item['route']) : request()->routeIs($item['route'], $item['route'].'.*');
                            @endphp
                            <li>
                                <a href="{{ route($item['route']) }}" wire:navigate data-nav-link
                                    @if ($item['route'] !== $user->role->dashboardRoute()) @if ($exact) wire:current.exact @else wire:current @endif @endif
                                    @if ($active) aria-current="page" data-current @endif
                                    class="group relative flex h-11 items-center gap-3 rounded-md px-3 text-sm font-medium text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 lg:h-10 data-current:bg-mint-50 data-current:text-mint-700 data-current:before:absolute data-current:before:inset-y-2 data-current:before:left-0 data-current:before:w-[3px] data-current:before:rounded-full data-current:before:bg-mint-700">
                                    {{ svg('heroicon-m-'.$item['icon'], 'size-5 shrink-0') }}
                                    <span class="truncate" x-bind:class="{!! $tip !!}">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endforeach
            </nav>

            <div class="hidden shrink-0 border-t border-neutral-200 p-3 lg:block">
                <button type="button" x-on:click="collapsed = ! collapsed" aria-controls="sidebar" x-bind:aria-expanded="(! collapsed).toString()" aria-expanded="true"
                    class="group relative flex h-10 w-full items-center gap-3 rounded-md px-3 text-sm font-medium text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900">
                    <span class="inline-flex shrink-0 transition-transform duration-[var(--dur-base)]" x-bind:class="collapsed && 'rotate-180'">{{ svg('heroicon-m-chevron-double-left', 'size-5') }}</span>
                    <span class="truncate" x-bind:class="{!! $tip !!}" x-text="collapsed ? 'Luaskan menu' : 'Ciutkan menu'">Ciutkan menu</span>
                </button>
            </div>
        </aside>
    </div>
    @endpersist

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-neutral-200 bg-white px-4 md:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-2">
                <button type="button" x-data x-on:click="$store.nav.open = true" aria-label="Buka menu" aria-controls="sidebar"
                    aria-expanded="false" x-bind:aria-expanded="$store.nav.open.toString()"
                    class="-ml-2 inline-flex size-11 shrink-0 items-center justify-center rounded-md text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 lg:hidden">
                    {{ svg('heroicon-o-bars-3', 'size-6') }}
                </button>
                @if ($breadcrumbs)
                    <nav aria-label="Breadcrumb" class="hidden min-w-0 sm:block">
                        <ol role="list" class="flex min-w-0 items-center gap-1.5 text-sm">
                            @foreach ($breadcrumbs as $label => $url)
                                <li class="flex min-w-0 items-center gap-1.5">
                                    @if (! $loop->first){{ svg('heroicon-m-chevron-right', 'size-4 shrink-0 text-neutral-500') }}@endif
                                    @if ($url && ! $loop->last)
                                        <a href="{{ $url }}" wire:navigate class="whitespace-nowrap text-mint-700 hover:underline">{{ $label }}</a>
                                    @elseif ($loop->last)
                                        <span aria-current="page" class="truncate font-medium text-neutral-900">{{ $label }}</span>
                                    @else
                                        <span class="whitespace-nowrap text-neutral-600">{{ $label }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                @endif
            </div>
            {{-- Bell and year switcher are deferred (DECISIONS.md). --}}
            <div class="flex min-w-0 items-center gap-3">
                <span class="hidden size-9 shrink-0 sm:flex items-center justify-center rounded-full bg-mint-100 text-sm font-semibold text-mint-800" aria-hidden="true">{{ $user->initials() }}</span>
                <div class="min-w-0 leading-tight">
                    <p title="{{ $user->name }}" class="max-w-[6.5rem] truncate text-sm font-medium text-neutral-900 sm:max-w-xs">{{ $user->name }}</p>
                    <p class="text-xs text-neutral-600">{{ $user->role->label() }}</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                    @csrf
                    <x-ui.button variant="secondary" icon="arrow-right-start-on-rectangle">Keluar</x-ui.button>
                </form>
            </div>
        </header>

        <main id="konten" class="w-full max-w-[1600px] flex-1 px-4 py-6 md:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>

    <x-ui.toast />
    @livewireScriptConfig
</body>
</html>
