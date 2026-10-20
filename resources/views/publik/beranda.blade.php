{{-- Public Home (design/public/landing.md): hero carousel → summary stats (RealisasiPublik::ringkasan, current Jakarta year) → link cards.
     Motion: hero content entrance (data-motion="hero"), stats and link-card stagger, count-up, CSS crossfade/Ken Burns, card-lift. --}}
@php
    $jumlah = count($slides);
    $tinggi = 'min-h-[420px] md:min-h-[480px] lg:min-h-[560px]';
    $isi = 'relative mx-auto flex w-full max-w-[1200px] items-end px-4 pt-16 md:px-6 '.$tinggi.($jumlah > 1 ? ' pb-24 lg:pb-36' : ' pb-12 lg:pb-24');
    $kontrol = 'inline-flex size-11 shrink-0 items-center justify-center rounded-md bg-white/10 text-white hover:bg-white/20 focus-visible:outline-accent-400';
    $overline = 'text-xs font-semibold uppercase tracking-[0.08em] text-accent-400';
    $judul = 'mt-3 font-display text-[32px] leading-10 font-extrabold text-white md:text-5xl md:leading-[56px]';
@endphp

<x-layouts::public>
    {{-- Hero. Text over photos always sits on the mint-950 scrim (≥8.12:1, DESIGN §3.4). --}}
    <section aria-label="Sorotan" aria-roledescription="carousel" data-motion="hero"
        @if ($jumlah > 1)
            x-data="carousel({ count: {{ $jumlah }}, interval: 7000 })"
            x-bind:class="{ 'hero-jeda': ! running }"
            x-on:pointerenter="enter($event)" x-on:pointerleave="leave($event)"
            x-on:focusin="focusIn()" x-on:focusout="focusOut($event)"
            x-on:pointerdown="down($event)" x-on:pointerup="up($event)" x-on:pointercancel="x0 = null"
            x-on:keydown.left="prev()" x-on:keydown.right="next()"
        @endif
        class="relative isolate touch-pan-y overflow-hidden bg-mint-950">
        @if ($jumlah === 0)
            <div class="{{ $isi }}">
                <div class="max-w-[640px]">
                    <span class="block h-[3px] w-10 bg-accent-400" aria-hidden="true" data-hero-part></span>
                    <p class="mt-4 {{ $overline }}" data-hero-part>INSPEKTORAT DAERAH</p>
                    <h1 class="{{ $judul }}" data-hero-part>Mengawal akuntabilitas pemerintahan Kabupaten Muara Enim</h1>
                </div>
            </div>
        @else
            <div class="grid" @if ($jumlah > 1) aria-live="off" x-bind:aria-live="playing ? 'off' : 'polite'" @endif>
                @foreach ($slides as $n => $s)
                    @php $hero = $n === 0 ? 'data-hero-part' : ''; @endphp
                    <div role="group" aria-roledescription="slide" aria-label="{{ $n + 1 }} dari {{ $jumlah }}"
                        @if ($n > 0) aria-hidden="true" @endif
                        @if ($jumlah > 1) x-bind:aria-hidden="(i !== {{ $n }}).toString()" x-bind:class="{ 'z-[1] opacity-100': i === {{ $n }}, 'pointer-events-none opacity-0': i !== {{ $n }} }" @endif
                        @class(['hero-slide relative col-start-1 row-start-1 overflow-hidden', 'z-[1] opacity-100' => $n === 0, 'pointer-events-none opacity-0' => $n > 0])>
                        <img src="{{ $s['src1920'] }}" srcset="{{ $s['src960'] }} 960w, {{ $s['src1920'] }} 1920w" sizes="100vw" alt="{{ $s['alt'] }}"
                            @if ($n === 0) fetchpriority="high" @else loading="lazy" @endif draggable="false"
                            @if ($jumlah > 1) x-bind:class="{ 'ken-burns': i === {{ $n }} }" @endif
                            @class(['absolute inset-0 size-full object-cover', 'ken-burns' => $n === 0])>
                        <div class="absolute inset-0 bg-mint-950/80 md:bg-transparent md:bg-linear-to-r md:from-mint-950/90 md:via-mint-950/80 md:to-mint-950/40" aria-hidden="true"></div>
                        <div class="{{ $isi }}">
                            <div class="max-w-[640px]">
                                <span class="block h-[3px] w-10 bg-accent-400" aria-hidden="true" {{ $hero }}></span>
                                <p class="mt-4 {{ $overline }}" {{ $hero }}>INSPEKTORAT DAERAH</p>
                                @if ($n === 0)
                                    <h1 class="{{ $judul }}" data-hero-part>{{ $s['judul'] }}</h1>
                                @else
                                    <p class="{{ $judul }}">{{ $s['judul'] }}</p>
                                @endif
                                @if ($s['kutipan'] && $s['pemilik'])
                                    <figure class="mt-4" {{ $hero }}>
                                        <blockquote class="text-lg leading-7 text-mint-200"><p>{{ $s['kutipan'] }}</p></blockquote>
                                        <figcaption class="mt-2 text-sm text-mint-300">— {{ $s['pemilik'] }}</figcaption>
                                    </figure>
                                @elseif ($s['kutipan'])
                                    <p class="mt-4 text-lg leading-7 text-mint-200" {{ $hero }}>{{ $s['kutipan'] }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- "Delapan Sungai" motif on the right edge, mint-200 at 12%; only ≥1024 where it never sits behind the 640px text column. --}}
        <svg class="pointer-events-none absolute inset-y-0 right-0 z-[2] hidden h-full w-1/3 text-mint-200 opacity-[.12] lg:block" viewBox="0 0 200 400"
            preserveAspectRatio="none" fill="none" aria-hidden="true" focusable="false">
            @foreach (range(1, 8) as $k)
                @php $x = 20 + $k * 20; @endphp
                <path d="M{{ $x }} 0 C {{ $x - 14 }} 60, {{ $x + 14 }} 120, {{ $x }} 180 S {{ $x - 14 }} 300, {{ $x }} 400" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke" />
            @endforeach
        </svg>

        @if ($jumlah > 1)
            {{-- Controls (Pause/Putar first in tab order); useless without JS, so x-cloak keeps them hidden then. --}}
            <div x-cloak class="absolute inset-x-0 bottom-4 z-[3] lg:bottom-16" data-hero-part>
                <div class="mx-auto flex max-w-[1200px] items-center gap-2 px-4 md:px-6 lg:justify-end">
                    <button type="button" x-on:click="toggle()" x-bind:aria-label="playing ? 'Jeda slide' : 'Putar slide'" aria-label="Jeda slide" class="{{ $kontrol }}">
                        <span x-show="playing">{{ svg('heroicon-o-pause', 'size-6', ['aria-hidden' => 'true']) }}</span>
                        <span x-show="! playing">{{ svg('heroicon-o-play', 'size-6', ['aria-hidden' => 'true']) }}</span>
                    </button>
                    <div class="flex flex-1 items-center justify-center lg:flex-none">
                        @foreach ($slides as $n => $s)
                            <button type="button" x-on:click="go({{ $n }})" aria-label="Slide {{ $n + 1 }} dari {{ $jumlah }}"
                                x-bind:aria-current="i === {{ $n }} ? 'true' : null" @if ($n === 0) aria-current="true" @endif
                                class="group inline-flex h-11 min-w-6 items-center justify-center rounded-md px-1 focus-visible:outline-accent-400">
                                <span x-bind:class="{ 'w-6 bg-white': i === {{ $n }}, 'w-2 bg-white/50 group-hover:bg-white/80': i !== {{ $n }} }"
                                    @class(['block h-2 rounded-full', 'w-6 bg-white' => $n === 0, 'w-2 bg-white/50' => $n > 0])></span>
                            </button>
                        @endforeach
                    </div>
                    <button type="button" x-on:click="prev()" aria-label="Slide sebelumnya" class="{{ $kontrol }}">{{ svg('heroicon-o-chevron-left', 'size-6', ['aria-hidden' => 'true']) }}</button>
                    <button type="button" x-on:click="next()" aria-label="Slide berikutnya" class="{{ $kontrol }}">{{ svg('heroicon-o-chevron-right', 'size-6', ['aria-hidden' => 'true']) }}</button>
                </div>
            </div>
        @endif
    </section>

    {{-- Summary stats: a white band overlapping the hero by 48px ≥1024. --}}
    <section aria-labelledby="ringkasan-judul" class="relative z-10 mx-auto max-w-[1200px] px-4 pt-8 md:px-6 lg:-mt-12 lg:pt-0">
        <h2 id="ringkasan-judul" class="sr-only">Ringkasan pengawasan {{ $tahun }}</h2>
        <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-md">
            @if ($ringkasan !== null && $ringkasan['item'] === 0)
                <p class="p-5 text-neutral-600">Belum ada PKPT yang disahkan untuk {{ $tahun }}.</p>
            @else
                <div data-motion="stagger" class="grid divide-y divide-neutral-200 md:grid-cols-3 md:divide-x md:divide-y-0">
                    <x-ui.stat-card variant="band" :label="'Item PKPT '.$tahun" :value="$ringkasan['item'] ?? null" :count-up="true" />
                    <x-ui.stat-card variant="band" label="SPT Reguler Selesai" :value="$ringkasan['selesai'] ?? null" :count-up="true" />
                    <x-ui.stat-card variant="band" label="Persentase Realisasi" :value="$ringkasan['persen'] ?? null" :desimal="1" akhiran="%" :count-up="true">
                        @if ($ringkasan !== null)
                            <x-ui.progress class="mt-3" :nilai="$ringkasan['selesai']" :maks="$ringkasan['rencana']"
                                :label="number_format($ringkasan['selesai'], 0, ',', '.').' dari '.number_format($ringkasan['rencana'], 0, ',', '.').' SPT yang direncanakan'" />
                        @endif
                    </x-ui.stat-card>
                </div>
            @endif
        </div>
        <p class="mt-3 text-xs text-neutral-500">{{ $ringkasan === null ? 'Data belum tersedia' : 'Data per '.$tanggal }}</p>
    </section>

    {{-- Link cards: the whole card is one link (card-lift). Illustrations are decorative. --}}
    <section aria-label="Jelajahi data" class="mx-auto max-w-[1200px] px-4 py-16 md:px-6 lg:py-24">
        <div data-motion="stagger" class="grid gap-4 md:grid-cols-2 lg:gap-6">
            <x-ui.card :href="url('/jadwal')" variant="mint" lift class="flex flex-col gap-5 lg:p-8">
                <svg viewBox="0 0 240 72" class="h-[72px] w-full max-w-60" aria-hidden="true" focusable="false">
                    <rect x="0" y="4" width="110" height="10" rx="4" class="fill-cat-1" />
                    <rect x="50" y="22" width="120" height="10" rx="4" class="fill-cat-2" />
                    <rect x="20" y="40" width="90" height="10" rx="4" class="fill-cat-3" />
                    <rect x="120" y="58" width="110" height="10" rx="4" class="fill-cat-4" />
                </svg>
                <div>
                    <h2 class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Jadwal PKPT</h2>
                    <span class="mt-2 block text-neutral-600">Lihat siapa memeriksa apa dan kapan, sepanjang tahun.</span>
                </div>
                <span class="mt-auto font-medium text-mint-700">Buka jadwal →</span>
            </x-ui.card>
            <x-ui.card :href="url('/realisasi')" lift class="flex flex-col gap-5 lg:p-8">
                <svg viewBox="0 0 240 72" class="h-[72px] w-full max-w-60" aria-hidden="true" focusable="false">
                    @foreach ([[40, 30], [56, 44], [48, 20], [64, 52], [44, 36]] as $k => [$rencana, $realisasi])
                        @php $x = $k * 48; @endphp
                        <rect x="{{ $x }}" y="{{ 72 - $rencana }}" width="18" height="{{ $rencana }}" rx="3" class="fill-neutral-100 stroke-border-strong" stroke-width="1.5" />
                        <rect x="{{ $x + 20 }}" y="{{ 72 - $realisasi }}" width="18" height="{{ $realisasi }}" rx="3" class="fill-mint-700" />
                    @endforeach
                </svg>
                <div>
                    <h2 class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Realisasi Pengawasan</h2>
                    <span class="mt-2 block text-neutral-600">Lihat berapa banyak rencana pengawasan yang sudah terlaksana.</span>
                </div>
                <span class="mt-auto font-medium text-mint-700">Lihat realisasi →</span>
            </x-ui.card>
        </div>
    </section>
</x-layouts::public>
