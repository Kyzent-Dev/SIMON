{{-- Shared shell of the error pages (design/auth/login.md §1: brand band + white card, no sidebar). Needs no database: the 500 page must render when the DB is down.
     Props: $kode, $judul, $kalimat, $ikon, $tombol (label or null), $aksi ('beranda' | 'muat-ulang'). --}}
@php
    $tujuan = url('/');
    if ($tombol && ($aksi ?? 'beranda') === 'beranda') {
        // Logged in → dashboard, guest → beranda. The session lookup may fail on a broken database, so fall back to the root.
        try {
            $tujuan = auth()->check() ? route('dashboard') : route('beranda');
        } catch (\Throwable) {
        }
    }
    $kelasTombol = 'inline-flex h-11 items-center justify-center rounded-md bg-mint-700 px-4 text-sm font-medium text-white transition-colors duration-[var(--dur-fast)] hover:bg-mint-800 lg:h-10';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul }} · SINTAS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-dvh bg-neutral-25">
    <a href="#konten" class="sr-only z-[100] rounded-md bg-white px-4 py-2 font-medium text-mint-700 shadow-md focus:not-sr-only focus:fixed focus:top-2 focus:left-2">Lewati ke konten utama</a>

    <div class="min-h-dvh lg:grid lg:grid-cols-12">
        <header class="relative flex h-[120px] flex-col justify-center overflow-hidden bg-mint-950 px-4 pb-6 lg:col-span-5 lg:h-auto lg:px-12 lg:pb-0">
            <svg class="pointer-events-none absolute inset-0 size-full text-mint-200 opacity-15" viewBox="0 0 400 400" preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
                @foreach (range(1, 8) as $i)
                    @php $y = $i * 44; @endphp
                    <path d="M0 {{ $y }} C 50 {{ $y - 18 }}, 100 {{ $y + 18 }}, 150 {{ $y }} S 250 {{ $y - 18 }}, 300 {{ $y }} S 400 {{ $y + 18 }}, 450 {{ $y }}" stroke="currentColor" stroke-width="2" vector-effect="non-scaling-stroke" />
                @endforeach
            </svg>
            <div class="relative mx-auto w-full max-w-[420px] lg:mx-0 lg:max-w-md">
                <p class="font-display text-[1.75rem]/9 font-bold text-white lg:text-4xl/11">SINTAS</p>
                <p class="mt-3 hidden text-base text-mint-200 lg:block">Sistem Informasi Surat Tugas Pengawasan — Inspektorat Daerah Kabupaten Muara Enim</p>
                <div class="mt-6 hidden h-1 w-16 rounded-full bg-accent-400 lg:block" aria-hidden="true"></div>
            </div>
        </header>

        <main id="konten" class="relative -mt-6 flex justify-center px-4 pb-10 lg:col-span-7 lg:mt-0 lg:items-center lg:p-8">
            <div class="w-full max-w-[420px] rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5">
                <span class="inline-flex size-12 items-center justify-center rounded-lg bg-mint-100 text-mint-800">{{ svg('heroicon-o-'.$ikon, 'size-6') }}</span>
                <p class="mt-4 text-xs/4 font-medium text-neutral-500">Kode galat {{ $kode }}</p>
                <h1 class="mt-1 font-display text-2xl/8 font-bold text-neutral-900 lg:text-[1.75rem]/9">{{ $judul }}</h1>
                <p class="mt-2 text-base/6 text-neutral-600">{{ $kalimat }}</p>
                @if ($tombol)
                    <div class="mt-6">
                        @if (($aksi ?? 'beranda') === 'muat-ulang')
                            <button type="button" onclick="location.reload()" class="{{ $kelasTombol }}">{{ $tombol }}</button>
                        @else
                            <a href="{{ $tujuan }}" class="{{ $kelasTombol }}">{{ $tombol }}</a>
                        @endif
                    </div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
