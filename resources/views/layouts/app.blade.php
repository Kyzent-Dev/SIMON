@php
    $user = auth()->user();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
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
<body class="min-h-dvh bg-neutral-25">
    <a href="#konten" class="sr-only z-[100] rounded-md bg-white px-4 py-2 font-medium text-mint-700 shadow-md focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Lewati ke konten utama</a>

    {{-- _shared.md §1 top header, minimal: no sidebar/bell/breadcrumbs/year switcher yet (DECISIONS.md). --}}
    <header class="flex h-16 items-center justify-between gap-3 border-b border-neutral-200 bg-white px-4 md:px-6 lg:px-8">
        <p class="shrink-0 whitespace-nowrap font-display text-lg font-bold text-mint-800">SIMON-SPT</p>
        <div class="flex min-w-0 items-center gap-3">
            <span class="hidden size-9 shrink-0 sm:flex items-center justify-center rounded-full bg-mint-100 text-sm font-semibold text-mint-800" aria-hidden="true">{{ $initials }}</span>
            <div class="min-w-0 leading-tight">
                <p class="max-w-[6.5rem] truncate text-sm font-medium text-neutral-900 sm:max-w-xs">{{ $user->name }}</p>
                <p class="text-xs text-neutral-700">{{ $user->role->label() }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                @csrf
                <x-ui.button variant="secondary" icon="arrow-right-start-on-rectangle">Keluar</x-ui.button>
            </form>
        </div>
    </header>

    <main id="konten" class="px-4 py-6 md:px-6 lg:px-8">
        {{ $slot }}
    </main>
    @livewireScriptConfig
</body>
</html>
