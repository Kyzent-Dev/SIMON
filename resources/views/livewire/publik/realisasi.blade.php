{{-- Public Realisasi SPT (design/public/realisasi.md): title → filter bar (sticky ≥768, bottom drawer <768) + chips → KPI line → line chart
     → donut + bar → table (cards <768). Chart cards fade up (data-motion) on the first render only: a Livewire update never carries it, so
     filter changes swap instantly while the charts animate their data (200ms, `grafik-publik`). --}}
@php
    $n = fn ($v) => number_format((int) $v, 0, ',', '.');
    $pct = fn ($p) => $p === null ? '–' : number_format($p, 1, ',', '.').'%';
    $namaBulan = fn (int $b) => \Carbon\CarbonImmutable::create(2000, $b, 1)->translatedFormat('F');
    $twLabel = ['1' => 'TW I', '2' => 'TW II', '3' => 'TW III', '4' => 'TW IV'];

    $tahunOpsi = collect($opsi['tahun'])->mapWithKeys(fn ($t) => [$t => (string) $t])->all();
    $twOpsi = $twLabel;
    $bulanOpsi = collect($tw === '' ? range(1, 12) : range((int) $tw * 3 - 2, (int) $tw * 3))->mapWithKeys(fn ($b) => [$b => $namaBulan($b)])->all();
    $irbanOpsi = collect($opsi['irban'])->pluck('nama', 'id')->all();
    $wilayahOpsi = collect($opsi['wilayah'])->pluck('nama', 'id')->all();
    $jenisOpsi = collect($opsi['jenis'])->pluck('nama', 'id')->all();

    $chip = array_filter([
        'tw' => $tw !== '' ? $twLabel[$tw] : null,
        'bulan' => $bulan !== '' ? $namaBulan((int) $bulan) : null,
        'irban' => $irbanOpsi[$irban] ?? null,
        'wilayah' => $wilayahOpsi[$wilayah] ?? null,
        'jenis' => $jenisOpsi[$jenis] ?? null,
    ]);
    $ringkasanFilter = implode(' · ', [$tahun, ...array_values($chip)]);
    $muat = 'tahun,tw,bulan,irban,wilayah,jenis,hapus,aturUlang';

    // Irban series colours (DESIGN §9) as tokens: I mint-800, II cat-2, III cat-11, IV cat-4, V cat-5; Lainnya cat-other.
    $titikIrban = ['bg-mint-800', 'bg-cat-2', 'bg-cat-11', 'bg-cat-4', 'bg-cat-5'];

    if ($garis) {
        $totalBulan = array_map(fn (...$k) => array_sum($k), ...array_column($garis['seri'], 'data'));
        $puncak = max($totalBulan);
        $labelGaris = "Grafik garis: SPT selesai per bulan {$tahun}".($perIrban ? ' per Irban' : '').', '
            .($puncak > 0 ? 'tertinggi '.$namaBulan(array_search($puncak, $totalBulan) + 1)." dengan {$n($puncak)} SPT" : 'belum ada SPT selesai');
    }
    $menurut = $proporsi === 'jenis' ? 'Jenis Penugasan' : 'Irban';
    if ($donat) {
        $totalDonat = array_sum(array_column($donat['irisan'], 'jumlah'));
        $labelDonat = $totalDonat > 0
            ? "Grafik donat: proporsi {$n($totalDonat)} SPT selesai per {$menurut}, terbesar {$donat['irisan'][0]['label']} {$pct($donat['irisan'][0]['persen'])}"
            : "Grafik donat: proporsi SPT selesai per {$menurut}, belum ada SPT selesai";
    }
    if ($batang) {
        $labelBatang = "Grafik batang: realisasi vs rencana PKPT per Irban, {$batang['periode']} {$tahun}, "
            .$n(array_sum($batang['realisasi'])).' dari '.$n(array_sum($batang['rencana'])).' SPT terealisasi';
        $ringkasanBatang = implode(' · ', array_filter([$batang['periode'].' '.$tahun, $chip['irban'] ?? null, $chip['jenis'] ?? null]));
    }
    $baris = $tabel?->items() ?? [];
    $adaNomor = $baris !== [] && array_key_exists('nomor', $baris[0]);
@endphp

<div class="mx-auto max-w-[1200px] px-4 py-8 md:px-6 lg:py-12">
    <h1 class="font-display text-2xl leading-8 font-bold text-neutral-900 md:text-[28px] md:leading-9">Realisasi Pengawasan {{ $tahun }}</h1>

    {{-- Filter bar: one sticky white bar ≥768 (two rows at 768–1023), a Filter (n) button + bottom drawer below. Focus lands here after a chip is removed. --}}
    <section id="filter-realisasi" tabindex="-1" aria-label="Filter realisasi"
        x-data x-on:fokus-filter.window="$nextTick(() => $el.focus())"
        class="z-20 -mx-4 mt-6 border-b border-neutral-200 bg-white px-4 py-3 focus:outline-none md:-mx-6 md:px-6 lg:sticky lg:top-16">
        <div class="hidden gap-3 md:grid md:grid-cols-3 lg:grid-cols-6">
            @include('livewire.publik.partials.filter-realisasi', ['sfx' => ''])
        </div>
        <x-ui.button type="button" variant="secondary" class="w-full md:hidden" x-on:click="$dispatch('open-modal', { name: 'filter-realisasi' })">
            {{ svg('heroicon-o-funnel', 'size-5', ['aria-hidden' => 'true']) }} Filter{{ $chip ? ' ('.count($chip).')' : '' }}
        </x-ui.button>
        @if ($chip)
            <ul role="list" aria-label="Filter aktif" class="mt-3 flex flex-wrap items-center gap-2">
                @foreach ($chip as $kunci => $teks)
                    <li>
                        <button type="button" wire:click="hapus('{{ $kunci }}')" aria-label="Hapus filter {{ $teks }}"
                            class="inline-flex min-h-11 items-center gap-1 rounded-md border border-neutral-200 bg-neutral-50 py-1 pr-1.5 pl-3 text-sm text-neutral-900 hover:bg-neutral-100 lg:min-h-8">
                            {{ $teks }} {{ svg('heroicon-m-x-mark', 'size-4 text-neutral-600', ['aria-hidden' => 'true']) }}
                        </button>
                    </li>
                @endforeach
                <li>
                    <button type="button" wire:click="aturUlang" class="inline-flex min-h-11 items-center rounded-md px-2 text-sm font-medium text-mint-700 hover:underline lg:min-h-8">Atur ulang</button>
                </li>
            </ul>
        @endif
    </section>

    <x-ui.drawer name="filter-realisasi" title="Filter" posisi="bawah">
        <div class="space-y-4">
            @include('livewire.publik.partials.filter-realisasi', ['sfx' => '-m'])
        </div>
        <x-slot:footer>
            <x-ui.button type="button" class="w-full" x-on:click="$dispatch('close-modal', { name: 'filter-realisasi' })">Terapkan</x-ui.button>
        </x-slot:footer>
    </x-ui.drawer>

    {{-- KPI line. mint-700 numbers on the page background (DESIGN §3.4 pair, 5.49:1; not realisasi.md's mint-800, see decisions/fase-9.md Task 5). --}}
    @php $angka = 'font-display text-[28px] leading-9 font-bold text-mint-700 tabular-nums'; @endphp
    @if ($kpi)
        <p class="mt-6 text-base text-neutral-900"><span class="{{ $angka }}">{{ $n($kpi['selesai']) }}</span> SPT selesai dari <span class="{{ $angka }}">{{ $n($kpi['rencana']) }}</span> SPT yang direncanakan · <span class="{{ $angka }}">{{ $pct($kpi['persen']) }}</span> realisasi</p>
    @else
        <x-ui.alert variant="danger" class="mt-6">Ringkasan realisasi gagal dimuat.</x-ui.alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-12">
        <x-app.chart-card id="grafik-garis" judul="SPT Selesai per Bulan" :ringkasan="$ringkasanFilter" :label="$labelGaris ?? ''" :gagal="! $garis"
            :muat="$muat.',perIrban'" tinggi="h-[280px]" class="lg:col-span-12" :data-motion="$awal ? 'fade-up' : null">
            <x-slot:aksi>
                <x-ui.switch label="Per Irban" name="per-irban" :checked="$perIrban" wire:model.live="perIrban" />
            </x-slot:aksi>
            @if ($garis)
                <div wire:ignore x-data="grafikGaris(@js($garis))" x-on:grafik-publik.window="$event.detail.garis && perbarui($event.detail.garis)" class="relative h-full">
                    <canvas x-ref="kanvas" aria-hidden="true"></canvas>
                </div>
                <x-slot:tabel>
                    <thead class="bg-neutral-50 text-xs font-semibold">
                        <tr>
                            <th scope="col" class="h-9 px-3 text-left">Bulan</th>
                            @foreach ($garis['seri'] as $s)
                                <th scope="col" class="h-9 px-3 text-right whitespace-nowrap">{{ $perIrban ? $s['nama'] : 'SPT selesai' }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (range(0, 11) as $b)
                            <tr class="border-t border-neutral-200">
                                <th scope="row" class="h-9 px-3 text-left font-normal">{{ $namaBulan($b + 1) }}</th>
                                @foreach ($garis['seri'] as $s)
                                    <td class="px-3 text-right">{{ $n($s['data'][$b]) }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:tabel>
            @endif
        </x-app.chart-card>

        <x-app.chart-card id="grafik-donat" judul="Proporsi" :ringkasan="$ringkasanFilter" :label="$labelDonat ?? ''" :gagal="! $donat"
            :muat="$muat.',proporsi'" tinggi="h-60" class="lg:col-span-5" :data-motion="$awal ? 'fade-up' : null">
            <x-slot:aksi>
                <x-ui.segmented name="proporsi" legend="Proporsi menurut" :options="['irban' => 'per Irban', 'jenis' => 'per Jenis Penugasan']" :value="$proporsi"
                    wire:model.live="proporsi" penuh class="w-full" />
            </x-slot:aksi>
            @if ($donat)
                <div wire:ignore x-data="grafikDonat(@js($donat))" x-on:grafik-publik.window="$event.detail.donat && perbarui($event.detail.donat)" class="relative h-full">
                    <canvas x-ref="kanvas" aria-hidden="true"></canvas>
                </div>
                <x-slot:legenda>
                    @if ($donat['irisan'] === [])
                        <p class="mt-3 text-sm text-neutral-600">Belum ada SPT selesai.</p>
                    @else
                        <ul role="list" aria-label="Legenda proporsi" class="mt-4 space-y-2 text-sm">
                            @foreach ($donat['irisan'] as $s)
                                <li class="flex items-center gap-2">
                                    @if (is_string($s['warna']))
                                        <span aria-hidden="true" class="size-2.5 shrink-0 rounded-full bg-[var(--type)]" style="--type: {{ $s['warna'] }}"></span>
                                    @else
                                        <span aria-hidden="true" @class(['size-2.5 shrink-0 rounded-full', is_int($s['warna']) ? $titikIrban[$s['warna'] % 5] : 'bg-cat-other'])></span>
                                    @endif
                                    <span class="min-w-0 flex-1 text-neutral-900">{{ $s['label'] }}</span>
                                    <span class="text-neutral-600 tabular-nums whitespace-nowrap">{{ $n($s['jumlah']) }} SPT · {{ $pct($s['persen']) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-slot:legenda>
                <x-slot:tabel>
                    <thead class="bg-neutral-50 text-xs font-semibold">
                        <tr>
                            <th scope="col" class="h-9 px-3 text-left">{{ $menurut }}</th>
                            <th scope="col" class="h-9 px-3 text-right">SPT selesai</th>
                            <th scope="col" class="h-9 px-3 text-right">Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($donat['irisan'] as $s)
                            <tr class="border-t border-neutral-200">
                                <th scope="row" class="h-9 px-3 text-left font-normal">{{ $s['label'] }}</th>
                                <td class="px-3 text-right">{{ $n($s['jumlah']) }}</td>
                                <td class="px-3 text-right">{{ $pct($s['persen']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-neutral-200 font-semibold">
                            <th scope="row" class="h-9 px-3 text-left">Total</th>
                            <td class="px-3 text-right">{{ $n($totalDonat) }}</td>
                            <td class="px-3 text-right">{{ $totalDonat > 0 ? '100,0%' : '–' }}</td>
                        </tr>
                    </tbody>
                </x-slot:tabel>
            @endif
        </x-app.chart-card>

        <x-app.chart-card id="grafik-batang" judul="Realisasi vs Rencana PKPT per Irban" :ringkasan="$ringkasanBatang ?? null" :label="$labelBatang ?? ''" :gagal="! $batang"
            :muat="$muat.',twGrafik'" class="lg:col-span-7" :data-motion="$awal ? 'fade-up' : null">
            <x-slot:aksi>
                <x-ui.segmented name="tw-grafik" legend="Triwulan grafik" :options="$twLabel + ['0' => 'Setahun']" :value="$twGrafikEfektif" wire:model.live="twGrafik" />
            </x-slot:aksi>
            @if ($bulan !== '' || $wilayah !== '')
                <x-slot:keterangan>
                    <p class="mt-2 text-xs text-neutral-500">Tidak mengikuti filter Bulan dan Wilayah.</p>
                </x-slot:keterangan>
            @endif
            @if ($batang)
                <div wire:ignore x-data="grafikRealisasi(@js($batang))" x-on:grafik-publik.window="$event.detail.batang && perbarui($event.detail.batang)" class="relative h-full">
                    <canvas x-ref="kanvas" aria-hidden="true"></canvas>
                </div>
                <x-slot:tabel>
                    <thead class="bg-neutral-50 text-xs font-semibold">
                        <tr>
                            <th scope="col" class="h-9 px-3 text-left">Irban</th>
                            <th scope="col" class="h-9 px-3 text-right">Rencana</th>
                            <th scope="col" class="h-9 px-3 text-right">Realisasi</th>
                            <th scope="col" class="h-9 px-3 text-right">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($batang['label'] as $i => $label)
                            <tr class="border-t border-neutral-200">
                                <th scope="row" class="h-9 px-3 text-left font-normal whitespace-nowrap">{{ $label }}</th>
                                <td class="px-3 text-right">{{ $n($batang['rencana'][$i]) }}</td>
                                <td class="px-3 text-right">{{ $n($batang['realisasi'][$i]) }}</td>
                                <td class="px-3 text-right">{{ $batang['persen'][$i] === null ? '–' : $batang['persen'][$i].'%' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-slot:tabel>
            @endif
        </x-app.chart-card>
    </div>

    <section aria-labelledby="judul-rincian" class="mt-10">
        <h2 id="judul-rincian" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Rincian Realisasi</h2>

        @if ($tabel === null)
            <x-ui.alert variant="danger" class="mt-4">Tabel realisasi gagal dimuat.</x-ui.alert>
        @elseif ($tabel->total() === 0)
            <x-ui.card class="mt-4">
                <x-ui.empty-state icon="funnel" title="Tidak ada realisasi untuk filter ini.">
                    <x-ui.button type="button" wire:click="aturUlang">Atur ulang filter</x-ui.button>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div wire:loading.delay wire:target="{{ $muat }},urutkan,gotoPage,nextPage,previousPage" class="mt-4 space-y-3 rounded-lg border border-neutral-200 bg-white p-4 shadow-xs">
                <span class="sr-only" role="status">Memuat data…</span>
                @for ($k = 0; $k < 5; $k++)
                    <div aria-hidden="true" class="h-10 animate-shimmer rounded-md bg-[linear-gradient(90deg,var(--color-neutral-100),var(--color-neutral-50),var(--color-neutral-100))] bg-[length:200%_100%]"></div>
                @endfor
            </div>
            <div wire:loading.remove.delay wire:target="{{ $muat }},urutkan,gotoPage,nextPage,previousPage">
                <x-ui.table label="Rincian realisasi {{ $ringkasanFilter }}" class="mt-4 hidden md:block">
                    <x-slot:head>
                        @if ($adaNomor)
                            <x-ui.th>Nomor SPT</x-ui.th>
                        @endif
                        <x-ui.th>Jenis Penugasan</x-ui.th>
                        <x-ui.th>Irban</x-ui.th>
                        <x-ui.th>Wilayah</x-ui.th>
                        <x-ui.th sortable :direction="$urut" wire:click="urutkan">Tanggal Pelaksanaan</x-ui.th>
                        <x-ui.th>Triwulan</x-ui.th>
                    </x-slot:head>
                    @foreach ($baris as $r)
                        <tr>
                            @if ($adaNomor)
                                <td class="whitespace-nowrap">{{ $r['nomor'] }}</td>
                            @endif
                            <td><x-app.type-chip :color="$r['jenis']['warna']" :label="$r['jenis']['nama']" /></td>
                            <td class="whitespace-nowrap">{{ $r['irban'] }}</td>
                            <td>{{ $r['wilayah'] }}</td>
                            <td class="whitespace-nowrap">{{ $r['tanggal'] }}</td>
                            <td class="whitespace-nowrap">{{ $r['triwulan'] }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>

                {{-- Below 768 each row is a card: type chip on top, key–value list beneath. The sort button mirrors the table header (same `urut`). --}}
                <div class="mt-4 flex justify-end md:hidden">
                    <x-ui.button type="button" variant="secondary" size="sm" wire:click="urutkan" class="min-h-11">
                        {{ svg($urut === 'asc' ? 'heroicon-m-bars-arrow-up' : 'heroicon-m-bars-arrow-down', 'size-5', ['aria-hidden' => 'true']) }}
                        {{-- The action, not the state (the next click does this): currently newest first → "terlama dulu". --}}
                        Urutkan {{ $urut === 'asc' ? 'terbaru' : 'terlama' }} dulu
                    </x-ui.button>
                </div>
                <ul role="list" aria-label="Rincian realisasi" class="mt-3 space-y-3 md:hidden">
                    @foreach ($baris as $r)
                        <li class="rounded-lg border border-neutral-200 bg-white p-4 shadow-xs">
                            <x-app.type-chip :color="$r['jenis']['warna']" :label="$r['jenis']['nama']" class="font-semibold" />
                            <dl class="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                                @if ($adaNomor)
                                    <dt class="text-neutral-500">Nomor SPT</dt><dd class="text-neutral-900 tabular-nums break-words">{{ $r['nomor'] }}</dd>
                                @endif
                                <dt class="text-neutral-500">Irban</dt><dd class="text-neutral-900">{{ $r['irban'] }}</dd>
                                <dt class="text-neutral-500">Wilayah</dt><dd class="text-neutral-900">{{ $r['wilayah'] }}</dd>
                                <dt class="text-neutral-500">Tanggal</dt><dd class="text-neutral-900 tabular-nums">{{ $r['tanggal'] }}</dd>
                                <dt class="text-neutral-500">Triwulan</dt><dd class="text-neutral-900">{{ $r['triwulan'] }}</dd>
                            </dl>
                        </li>
                    @endforeach
                </ul>

                <x-ui.pagination :paginator="$tabel" :per-page-options="[]" class="mt-4" />
            </div>
        @endif
    </section>
</div>
