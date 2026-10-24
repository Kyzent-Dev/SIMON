{{-- Public Jadwal PKPT (design/public/jadwal.md): header + filters + Linimasa|Bulanan toggle → legend → timeline or monthly view → table twin
     → Wilayah Pengawasan. Both views are always rendered (content visible without JS); Alpine `jadwal` shows one. bar-grow
     (data-motion="bars") only on the first render: a Livewire update never carries it, so filter changes swap instantly. --}}
@use('App\Support\Tanggal')
@php
    $ruas = 'grid grid-cols-[240px_repeat(60,minmax(0,1fr))]';
    $gaya = fn (array $j) => "--type: {$j['warna']}; --type-fg: {$j['warna_teks']};";
    $penuh = fn ($d) => $d->translatedFormat('j F Y');
    // aria-label range per jadwal.md: "3 Maret–21 Maret 2026" (start year shown only when it differs).
    $label = fn (array $i) => "{$i['jenis']['nama']}, {$i['irban']}, {$i['wilayah']}, "
        .$i['mulai']->translatedFormat($i['mulai']->year === $i['selesai']->year ? 'j F' : 'j F Y').'–'.$penuh($i['selesai'])
        .($i['tambahan_ke'] ? ", Tambahan · Perubahan ke-{$i['tambahan_ke']}" : '');
    // Added by an approved Perubahan PKPT: only its number is public (never alasan, SK or usulan).
    $tambahan = fn (array $i) => $i['tambahan_ke'] ? "Tambahan · Perubahan ke-{$i['tambahan_ke']}" : null;
    $jenis = collect($item)->pluck('jenis')->unique('id')->values();
    $hariIni = $linimasa['hariIni'] ?? null;
    // Column 5 of a month with only 4 PKPT weeks (MingguPkpt) is empty and hatched; decorative, inside aria-hidden grids.
    $kosong = fn (int $k) => $k % 5 === 4 && ($linimasa['mingguBulan'][intdiv($k, 5) + 1] ?? 5) === 4;
    $arsir = 'bg-[repeating-linear-gradient(135deg,var(--color-neutral-200)_0_2px,transparent_2px_6px)]';
    // The irban-specific empty title only when the year really has a Disahkan PKPT (opsi.tahun is clamped to Disahkan years).
    $namaIrban = $irban !== '' && $opsi['adaDisahkan'] ? collect($opsi['irban'])->firstWhere('id', (int) $irban)['nama'] ?? null : null;
    $tahunOpsi = collect($opsi['tahun'])->mapWithKeys(fn ($t) => [$t => (string) $t])->all();
    $irbanOpsi = collect($opsi['irban'])->mapWithKeys(fn ($i) => [$i['id'] => $i['nama']])->all();
@endphp

<div x-data="jadwal" class="mx-auto max-w-[1200px] px-4 py-8 md:px-6 lg:py-12">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 id="judul-jadwal" tabindex="-1" class="font-display text-2xl leading-8 font-bold text-neutral-900 focus:outline-none md:text-[28px] md:leading-9">Jadwal PKPT {{ $tahunAngka }}</h1>
            <p class="mt-1 text-neutral-600">Program Kerja Pengawasan Tahunan yang telah disahkan</p>
        </div>
    </div>

    {{-- Filters: inline ≥768, a bottom drawer below. --}}
    <div class="mt-6 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
        <div class="hidden gap-3 md:flex">
            <x-ui.select label="Tahun" name="tahun" :options="$tahunOpsi" :value="$tahun" wire:model.live="tahun" class="lg:w-36" />
            <x-ui.select label="Irban" name="irban" :options="$irbanOpsi" :value="$irban" placeholder="Semua Irban" wire:model.live="irban" class="lg:w-56" />
        </div>
        <x-ui.button type="button" variant="secondary" class="w-full md:hidden" x-on:click="$dispatch('open-modal', { name: 'filter-jadwal' })">
            {{ svg('heroicon-o-funnel', 'size-5', ['aria-hidden' => 'true']) }} Filter
        </x-ui.button>
        <x-ui.segmented name="tampilan" legend="Tampilan jadwal" :options="['linimasa' => 'Linimasa', 'bulanan' => 'Bulanan']" value="linimasa"
            x-model="tampilan" penuh class="w-full md:w-auto" />
    </div>

    <x-ui.drawer name="filter-jadwal" title="Filter" posisi="bawah">
        <div class="space-y-4">
            <x-ui.select label="Tahun" name="tahun" id="tahun-m" :options="$tahunOpsi" :value="$tahun" wire:model.live="tahun" />
            <x-ui.select label="Irban" name="irban" id="irban-m" :options="$irbanOpsi" :value="$irban" placeholder="Semua Irban" wire:model.live="irban" />
        </div>
    </x-ui.drawer>

    {{-- Loading (filter change): skeleton of 6 rows; the header and filters stay. --}}
    <div wire:loading.delay wire:target="tahun,irban" class="mt-6 rounded-lg border border-neutral-200 bg-white p-4 shadow-xs">
        <span class="sr-only" role="status">Memuat jadwal…</span>
        <div class="space-y-3" aria-hidden="true">
            @for ($n = 0; $n < 6; $n++)
                <div class="h-9 animate-shimmer rounded-md bg-[linear-gradient(90deg,var(--color-neutral-100),var(--color-neutral-50),var(--color-neutral-100))] bg-[length:200%_100%]"></div>
            @endfor
        </div>
    </div>

    <div wire:loading.remove.delay wire:target="tahun,irban">
        @if ($gagal)
            <x-ui.alert variant="danger" class="mt-6">
                Jadwal gagal dimuat. Muat ulang halaman.
                <div class="mt-3"><x-ui.button type="button" variant="secondary" size="sm" wire:click="$refresh">Coba lagi</x-ui.button></div>
            </x-ui.alert>
        @elseif ($item === [])
            <x-ui.card class="mt-6">
                <x-ui.empty-state icon="calendar" :title="$namaIrban ? 'Belum ada jadwal '.$namaIrban.' pada tahun '.$tahunAngka : 'Belum ada PKPT yang disahkan untuk tahun '.$tahunAngka">
                    <x-ui.button type="button" x-on:click="const s = document.getElementById('tahun');
                        if (s && s.offsetParent) { s.focus() } else { $dispatch('open-modal', { name: 'filter-jadwal' }); setTimeout(() => document.getElementById('tahun-m')?.focus(), 300) }">Pilih tahun lain</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            {{-- Legend: toggle buttons; beyond 2 rows it collapses into "Lihat legenda (n)". --}}
            <div class="mt-6" x-data="{ lebih: false, semua: false,
                    ukur() { const ul = this.$refs.chips, d = ul.style.display; ul.style.display = ''; this.lebih = new Set([...ul.children].map(c => c.offsetTop)).size > 2; ul.style.display = d } }"
                x-init="ukur()" x-on:resize.window.debounce.150ms="ukur()" x-on:jadwal-dirender.window="ukur()">
                <button type="button" x-show="lebih && ! semua" x-cloak x-on:click="semua = true; $nextTick(() => $refs.chips.querySelector('button')?.focus())" aria-expanded="false"
                    class="inline-flex min-h-11 items-center gap-1 rounded-md text-sm font-medium text-mint-700 hover:underline">
                    Lihat legenda ({{ $jenis->count() }}) {{ svg('heroicon-m-chevron-down', 'size-5', ['aria-hidden' => 'true']) }}
                </button>
                <ul x-ref="chips" x-show="! lebih || semua" role="list" aria-label="Legenda jenis penugasan" class="flex flex-wrap gap-2">
                    @foreach ($jenis as $j)
                        <li>
                            <button type="button" data-jenis="{{ $j['id'] }}" aria-pressed="false" x-bind:aria-pressed="(sorot === {{ $j['id'] }}).toString()" x-on:click="sorotJenis({{ $j['id'] }})"
                                x-bind:class="sorot === {{ $j['id'] }} ? 'border-mint-700 bg-mint-50' : 'border-neutral-200 bg-white hover:bg-neutral-50'"
                                class="inline-flex min-h-11 items-center rounded-md border border-neutral-200 bg-white px-3 lg:min-h-9">
                                <x-app.type-chip :color="$j['warna']" :label="$j['nama']" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Timeline (Gantt): 12 months × Minggu 1–5. Scrolls inside its own container below 1040 px, first column sticky. --}}
            <div x-show="tampilan === 'linimasa'" class="relative mt-4 rounded-lg border border-neutral-200 bg-white shadow-xs"
                x-data="{ more: false, cek() { const b = this.$refs.gulir; this.more = b.scrollLeft + b.clientWidth < b.scrollWidth - 1 } }"
                x-init="$nextTick(() => cek())" x-on:resize.window="cek()" x-effect="tampilan === 'linimasa' && $nextTick(() => cek())">
                <div x-ref="gulir" x-on:scroll="cek(); posisi()" role="region" aria-label="Linimasa Jadwal PKPT {{ $tahunAngka }}" tabindex="0" class="overflow-x-auto rounded-lg">
                    <div class="min-w-[1040px]" @if ($awal) data-motion="bars" @endif>
                        <div class="{{ $ruas }} border-b border-neutral-200 bg-neutral-50 text-xs" aria-hidden="true">
                            <div class="sticky left-0 z-10 row-span-3 flex items-end bg-neutral-50 px-4 pb-1.5 font-medium text-neutral-500 shadow-[1px_0_0_var(--color-neutral-200)]">Jenis Penugasan</div>
                            <div class="relative col-span-60 h-6">
                                @if ($hariIni)
                                    <span class="absolute top-1 -translate-x-1/2 rounded-sm bg-mint-700 px-1.5 py-0.5 leading-none font-semibold whitespace-nowrap text-white"
                                        style="left: calc(({{ $hariIni }} - 0.5) / 60 * 100%)">Hari ini</span>
                                @endif
                            </div>
                            @for ($b = 1; $b <= 12; $b++)
                                <div class="col-span-5 border-l border-neutral-200 px-1.5 py-1 font-semibold text-neutral-900">{{ \Carbon\CarbonImmutable::create($tahunAngka, $b, 1)->translatedFormat('M') }}</div>
                            @endfor
                            @for ($k = 0; $k < 60; $k++)
                                <div @if ($kosong($k)) data-minggu-kosong @endif @class(['pb-1 text-center text-neutral-500 tabular-nums', $k % 5 === 0 ? 'border-l border-neutral-200' : 'border-l border-dotted border-neutral-100', $arsir => $kosong($k)])>{{ $kosong($k) ? '' : $k % 5 + 1 }}</div>
                            @endfor
                        </div>

                        <div class="relative">
                            <div class="absolute inset-y-0 right-0 left-[240px] grid grid-cols-[repeat(60,minmax(0,1fr))]" aria-hidden="true">
                                @for ($k = 0; $k < 60; $k++)
                                    <span @if ($kosong($k)) data-minggu-kosong @endif @class([
                                        'relative',
                                        $arsir => $kosong($k),
                                        $k % 5 === 0 ? 'border-l border-neutral-200' : 'border-l border-dotted border-neutral-100',
                                        'bg-mint-50' => $hariIni && intdiv($k, 5) === intdiv($hariIni - 1, 5),
                                    ])>
                                        @if ($hariIni === $k + 1)
                                            <span class="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 bg-mint-700"></span>
                                        @endif
                                    </span>
                                @endfor
                            </div>

                            @foreach ($linimasa['baris'] as $baris)
                                <div class="{{ $ruas }} relative border-b border-neutral-200 last:border-b-0" style="grid-template-rows: repeat({{ count($baris['lajur']) }}, 36px)">
                                    <div class="sticky left-0 z-10 flex flex-col justify-center bg-white px-4 shadow-[1px_0_0_var(--color-neutral-200)]" style="grid-row: 1 / span {{ count($baris['lajur']) }}; grid-column: 1">
                                        <span class="truncate text-sm font-semibold text-neutral-900">{{ $baris['jenis']['nama'] }}</span>
                                        <span class="text-xs text-neutral-500 tabular-nums">{{ $baris['jumlah'] }} item</span>
                                    </div>
                                    @foreach ($baris['lajur'] as $n => $lajur)
                                        @foreach ($lajur as $bar)
                                            @php $i = $bar['item']; @endphp
                                            @if ($bar['semula'])
                                                <span data-semula aria-hidden="true" x-bind:class="{ 'opacity-25': redup({{ $i['jenis']['id'] }}) }"
                                                    class="h-[22px] self-center rounded-md border-2 border-dashed border-[color-mix(in_srgb,var(--type)_70%,transparent)] transition-opacity duration-[var(--dur-fast)]"
                                                    style="{{ $gaya($i['jenis']) }} grid-row: {{ $n + 1 }}; grid-column: {{ $bar['semula'][0] + 1 }} / {{ $bar['semula'][1] + 2 }}"></span>
                                            @endif
                                            {{-- Label inside only when ≥96 px: 8 columns ≈ 107 px at the 1040 px minimum width (240 + 60 × 13.3). --}}
                                            @php $berlabel = $bar['selesai'] - $bar['mulai'] + 1 >= 8; @endphp
                                            <button type="button" data-bar data-pemicu aria-label="{{ $label($i) }}" @unless ($berlabel) title="{{ $i['irban'] }} · {{ $i['wilayah'] }}" @endunless
                                                x-on:click="buka({{ $i['id'] }}, $el)" x-bind:class="{ 'opacity-25': redup({{ $i['jenis']['id'] }}) }"
                                                class="relative z-[1] h-[22px] min-w-0 self-center truncate rounded-md bg-[var(--type)] px-2 text-left text-xs font-semibold text-[var(--type-fg)] transition-opacity duration-[var(--dur-fast)]"
                                                style="{{ $gaya($i['jenis']) }} grid-row: {{ $n + 1 }}; grid-column: {{ $bar['mulai'] + 1 }} / {{ $bar['selesai'] + 2 }}">@if ($berlabel){{ $i['irban'] }} · {{ $i['wilayah'] }}@endif</button>
                                        @endforeach
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div x-show="more" x-cloak aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-0 w-8 rounded-r-lg bg-linear-to-l from-white to-transparent"></div>
            </div>

            {{-- Monthly view: 12 accordion cards, the current month (or January for other years) open. --}}
            <div x-show="tampilan === 'bulanan'" class="mt-4 space-y-3">
                @foreach ($bulanan as $b)
                    <x-ui.card x-data="{ terbuka: {{ $b['bulan'] === $bulanIni ? 'true' : 'false' }} }">
                        <h2>
                            <button type="button" x-on:click="terbuka = ! terbuka" aria-expanded="{{ $b['bulan'] === $bulanIni ? 'true' : 'false' }}"
                                x-bind:aria-expanded="terbuka.toString()" aria-controls="bulan-{{ $b['bulan'] }}"
                                class="-my-2 flex min-h-11 w-full items-center justify-between gap-3 rounded-md text-left">
                                <span class="text-base font-semibold text-neutral-900 tabular-nums">{{ $b['nama'] }} {{ $tahunAngka }} · {{ $b['jumlah'] }} penugasan</span>
                                <span x-bind:class="terbuka && 'rotate-180'" class="text-neutral-600">{{ svg('heroicon-m-chevron-down', 'size-5', ['aria-hidden' => 'true']) }}</span>
                            </button>
                        </h2>
                        <div id="bulan-{{ $b['bulan'] }}" x-show="terbuka" x-collapse.duration.150ms>
                            <div class="pt-4">
                                @forelse ($b['minggu'] as $m => $daftar)
                                    <p class="mt-3 mb-1 text-xs font-semibold tracking-[0.08em] text-neutral-600 uppercase first:mt-0">Minggu {{ $m }}</p>
                                    <ul role="list">
                                        @foreach ($daftar as $i)
                                            <li>
                                                <button type="button" data-pemicu x-on:click="buka({{ $i['id'] }}, $el)" x-bind:class="{ 'opacity-25': redup({{ $i['jenis']['id'] }}) }"
                                                    style="{{ $gaya($i['jenis']) }}" class="flex w-full gap-3 rounded-md py-2 pr-2 text-left transition-opacity duration-[var(--dur-fast)] hover:bg-neutral-50">
                                                    <span aria-hidden="true" class="w-1 shrink-0 self-stretch rounded-full bg-[var(--type)]"></span>
                                                    <span class="min-w-0">
                                                        <span class="block text-sm font-semibold text-neutral-900">{{ $i['jenis']['nama'] }}</span>
                                                        <span class="block text-[13px] text-neutral-600">{{ $i['irban'] }} · {{ $i['wilayah'] }}</span>
                                                        <span class="block text-xs text-neutral-500 tabular-nums">{{ Tanggal::rentang($i['mulai'], $i['selesai']) }}</span>
                                                        @if ($t = $tambahan($i))<span class="block text-xs font-medium text-neutral-600">{{ $t }}</span>@endif
                                                    </span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                @empty
                                    <p class="text-sm text-neutral-600">Tidak ada penugasan pada bulan ini.</p>
                                @endforelse
                            </div>
                        </div>
                    </x-ui.card>
                @endforeach
            </div>

            {{-- Table twin: always in the DOM, sr-only until revealed. --}}
            <div class="mt-4" x-data="{ tabel: false }">
                <button type="button" x-on:click="tabel = ! tabel" aria-expanded="false" x-bind:aria-expanded="tabel.toString()" aria-controls="tabel-jadwal"
                    class="inline-flex min-h-11 items-center rounded-md text-sm font-medium text-mint-700 hover:underline">
                    <span x-text="tabel ? 'Sembunyikan tabel' : 'Lihat sebagai tabel'">Lihat sebagai tabel</span>
                </button>
                <div id="tabel-jadwal" role="region" aria-label="Tabel Jadwal PKPT {{ $tahunAngka }}" tabindex="-1" x-bind:tabindex="tabel ? 0 : -1"
                    x-bind:class="{ 'sr-only': ! tabel }" class="sr-only relative mt-2 overflow-x-auto rounded-lg border border-neutral-200 bg-white shadow-xs">
                    <table class="w-full border-collapse text-left text-sm text-neutral-900 tabular-nums [&_tr>:first-child]:sticky [&_tr>:first-child]:left-0 [&_td:first-child]:bg-white [&_th:first-child]:bg-neutral-50">
                        <caption class="sr-only">Jadwal PKPT {{ $tahunAngka }}</caption>
                        <thead class="bg-neutral-50">
                            <tr class="[&>th]:px-4 [&>th]:py-3 [&>th]:font-semibold">
                                <th scope="col">Jenis Penugasan</th><th scope="col">Irban</th><th scope="col">Wilayah</th>
                                <th scope="col">Mulai</th><th scope="col">Selesai</th><th scope="col">Progres</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($item as $i)
                                <tr class="border-t border-neutral-200 [&>td]:h-12 [&>td]:px-4 [&>td]:py-1">
                                    <td><x-app.type-chip :color="$i['jenis']['warna']" :label="$i['jenis']['nama']" />@if ($t = $tambahan($i))<span class="block text-xs text-neutral-600">{{ $t }}</span>@endif</td>
                                    <td class="whitespace-nowrap">{{ $i['irban'] }}</td>
                                    <td>{{ $i['wilayah'] }}</td>
                                    <td class="whitespace-nowrap">{{ $penuh($i['mulai']) }}</td>
                                    <td class="whitespace-nowrap">{{ $penuh($i['selesai']) }}</td>
                                    <td class="whitespace-nowrap">{{ $i['selesaiSpt'] }} dari {{ $i['target'] }} SPT selesai</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Detail: popover anchored to the trigger ≥768, bottom sheet <768. tutup() returns focus to the open trigger (x-trap.noreturn, so an outside click on a control keeps its focus). --}}
            <div x-show="aktif !== null" x-cloak x-on:click="tutup()" aria-hidden="true" class="fixed inset-0 z-[60] bg-scrim md:hidden"></div>
            <div x-ref="detail" x-show="aktif !== null" x-cloak x-trap.noreturn="aktif !== null" role="dialog" aria-modal="true" x-bind:aria-labelledby="'detail-' + aktif"
                x-bind:style="gayaDetail" x-on:keydown.escape.window="aktif !== null && tutup()" x-on:resize.window="posisi()" x-on:scroll.window.passive="posisi()"
                x-on:click.outside="luar($event)"
                class="fixed inset-x-0 bottom-0 z-[70] max-h-[85dvh] overflow-y-auto rounded-t-xl bg-white p-5 shadow-lg md:inset-x-auto md:bottom-auto md:z-50 md:w-80 md:rounded-lg md:border md:border-neutral-200 md:p-4 md:shadow-md">
                @foreach ($item as $i)
                    <div x-show="aktif === {{ $i['id'] }}">
                        <h2 id="detail-{{ $i['id'] }}"><x-app.type-chip :color="$i['jenis']['warna']" :label="$i['jenis']['nama']" class="text-base font-semibold" /></h2>
                        @if ($t = $tambahan($i))
                            <x-app.tag icon="plus-circle" variant="info" class="mt-2">{{ $t }}</x-app.tag>
                        @endif
                        <dl class="mt-3 space-y-2 text-sm">
                            <div><dt class="text-xs text-neutral-500">Irban</dt><dd class="text-neutral-900">{{ $i['irban'] }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">Jadwal</dt><dd class="text-neutral-900 tabular-nums">{{ $penuh($i['mulai']) }} – {{ $penuh($i['selesai']) }}</dd></div>
                            <div><dt class="text-xs text-neutral-500">Wilayah</dt><dd class="text-neutral-900">{{ $i['wilayah'] }}</dd></div>
                        </dl>
                        @if ($i['semula'])
                            <p class="mt-3 flex items-center gap-2 text-sm text-neutral-600 tabular-nums" style="{{ $gaya($i['jenis']) }}">
                                <span aria-hidden="true" class="inline-block h-2.5 w-4 shrink-0 rounded-sm border-2 border-dashed border-[color-mix(in_srgb,var(--type)_70%,transparent)]"></span>
                                Jadwal direvisi: semula {{ Tanggal::rentang($i['semula'][0], $i['semula'][1]) }}
                            </p>
                        @endif
                        <x-ui.progress class="mt-4" :nilai="$i['selesaiSpt']" :maks="$i['target']" :label="$i['selesaiSpt'].' dari '.$i['target'].' SPT selesai'" />
                    </div>
                @endforeach
                <div class="mt-4 flex justify-end">
                    <x-ui.button type="button" variant="secondary" x-on:click="tutup()">Tutup</x-ui.button>
                </div>
            </div>
        @endif
    </div>

    @if (! $gagal)
        <section aria-labelledby="judul-wilayah" class="mt-12">
            <h2 id="judul-wilayah" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Wilayah Pengawasan</h2>
            <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5">
                <x-ui.tabs :tabs="array_map('strval', array_column($wilayah, 'irban'))"
                    :active="(int) $wilayahTab" model="wilayahTab" label="Irban" panel="panel-wilayah" />
                @php $aktifWilayah = $wilayah[(int) $wilayahTab]['daftar'] ?? []; @endphp
                <div role="tabpanel" id="panel-wilayah" aria-labelledby="panel-wilayah-tab-{{ $wilayahTab }}" tabindex="0" class="grid gap-6 rounded-md lg:grid-cols-3">
                    @foreach ($aktifWilayah as $judul => $nama)
                        <div>
                            <h3 class="text-lg leading-[26px] font-semibold text-neutral-900">{{ $judul }} <span class="text-sm font-normal text-neutral-500 tabular-nums">({{ count($nama) }})</span></h3>
                            @if ($nama === [])
                                <p class="mt-2 text-sm text-neutral-600">Belum ada wilayah.</p>
                            @else
                                <ul role="list" class="mt-2 space-y-1 text-sm text-neutral-900">
                                    @foreach ($nama as $n)
                                        <li>{{ $n }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
</div>
