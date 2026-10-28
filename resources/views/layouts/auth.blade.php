<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($title) ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    @include('partials.ikon')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-dvh bg-neutral-25">
    <a href="#konten" class="sr-only z-[100] rounded-md bg-white px-4 py-2 font-medium text-mint-700 shadow-md focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Lewati ke konten utama</a>

    <div class="min-h-dvh lg:grid lg:grid-cols-12">
        {{-- design/auth/login.md §1: brand panel (lg, 5/12) or 120px band (<lg): logo 64px above the name, 48px beside it in the band; optional photo + scrim (partials/latar-masuk). --}}
        <header class="relative flex h-[120px] flex-col justify-center overflow-hidden bg-mint-950 px-4 pb-6 lg:col-span-5 lg:h-auto lg:px-12 lg:pb-0">
            @include('partials.latar-masuk', ['base' => (string) app(\App\Services\Settings::class)->get('masuk.latar')])
            <div class="relative mx-auto flex w-full max-w-[420px] items-center gap-3 lg:mx-0 lg:max-w-md lg:flex-col lg:items-start lg:gap-0">
                <x-app.logo class="h-12 lg:h-16" />
                <p class="font-display text-[1.75rem]/9 font-bold text-white lg:mt-6 lg:text-4xl/11">SINTAS</p>
                <p class="mt-3 hidden text-base text-mint-200 lg:block">Sistem Informasi Surat Tugas Pengawasan — Inspektorat Daerah Kabupaten Muara Enim</p>
                <div class="mt-6 hidden h-1 w-16 rounded-full bg-accent-400 lg:block" aria-hidden="true"></div>
            </div>
        </header>

        <main id="konten" class="relative -mt-6 flex justify-center px-4 pb-10 lg:col-span-7 lg:mt-0 lg:items-center lg:p-8">
            <div class="w-full max-w-[420px] rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScriptConfig
</body>
</html>
