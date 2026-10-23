{{-- Dasbor Pimpinan (design/dashboard/pimpinan.md Hierarchy, Stat cards, Chart, Approval list, Motion; _shared.md §1; DESIGN §7.2–7.4, §8, §9, §10).
     Read-only: no edit or approve controls. Motion: count-up on the cards (first load only) and the
     Chart.js draw-in; both off under prefers-reduced-motion. The chart canvas is wire:ignore and gets new data via the `grafik-realisasi` event;
     its summary (role="img") and table twin are server-rendered and always in the DOM. --}}
@php
    $persen = fn ($p) => $p === null ? '–' : $p.'%';
    $ringkasan = 'Grafik batang Realisasi vs Rencana per Irban, '.$grafik['periode'].' '.$this->tahun.'. '
        .collect($grafik['label'])->map(fn ($l, $i) => $l.': '.$grafik['realisasi'][$i].' dari '.$grafik['rencana'][$i].' ('.$persen($grafik['persen'][$i]).')')->implode('; ').'.';
    $twOpsi = ['1' => 'TW I', '2' => 'TW II', '3' => 'TW III', '4' => 'TW IV', '' => 'Setahun'];
@endphp
<div>
    <x-ui.page-header title="Dasbor Pimpinan" description="Ringkasan pelaksanaan SPT.">
        <x-slot:actions>
            <div class="w-28"><x-ui.select label="Tahun" name="tahun" id="dasbor-tahun" wire:model.live="tahun" :options="$tahunList" :value="$this->tahun" /></div>
        </x-slot:actions>
    </x-ui.page-header>

    <section aria-label="Ringkasan SPT {{ $this->tahun }}">
        <ul class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
            @foreach ($kartu as $k)
                <li class="flex" wire:key="kartu-{{ $loop->index }}">
                    <x-ui.stat-card class="w-full" :label="$k['label']" :value="$k['nilai']" :href="$k['href']" :variant="$k['alert'] ? 'alert' : 'default'" :count-up="$animasi" />
                </li>
            @endforeach
        </ul>
    </section>

    <div class="mt-6">
        <section aria-labelledby="grafik-judul" class="min-w-0 rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h2 id="grafik-judul" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">Realisasi vs Rencana per Irban</h2>
                    <p class="mt-1 text-sm text-neutral-600">{{ $grafik['periode'] }} {{ $this->tahun }} · SPT selesai terhadap rencana PKPT</p>
                </div>
                <x-ui.segmented name="tw" legend="Triwulan grafik" wire:model.live="tw" :options="$twOpsi" :value="$this->tw" />
            </div>

            @unless ($pkptDisahkan)
                <x-ui.alert variant="warning" class="mt-4">PKPT {{ $this->tahun }} belum disahkan; rencana belum dapat dihitung.</x-ui.alert>
            @endunless

            <div role="img" aria-label="{{ $ringkasan }}" class="mt-4">
                <div wire:ignore x-data="grafikRealisasi(@js($grafik))" x-on:grafik-realisasi.window="perbarui($event.detail.data)" class="relative h-72">
                    <canvas x-ref="kanvas" aria-hidden="true"></canvas>
                </div>
            </div>

            <div x-data="{ tabel: false }" class="mt-3">
                <button type="button" x-on:click="tabel = ! tabel" x-bind:aria-expanded="tabel ? 'true' : 'false'" aria-controls="grafik-tabel"
                    class="inline-flex min-h-11 items-center gap-1 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-8">
                    <span x-text="tabel ? 'Sembunyikan tabel' : 'Tampilkan tabel'">Tampilkan tabel</span>
                    {{ svg('heroicon-m-chevron-down', 'size-4') }}
                </button>
                {{-- Always in the DOM: sr-only until disclosed (DESIGN §9, §10). `relative` keeps sr-only from widening the page. --}}
                <div id="grafik-tabel" class="relative" x-bind:class="tabel ? 'mt-2' : 'sr-only'">
                    <table class="w-full border-collapse text-sm tabular-nums text-neutral-900">
                        <caption class="sr-only">Realisasi vs Rencana per Irban, {{ $grafik['periode'] }} {{ $this->tahun }}</caption>
                        <thead class="bg-neutral-50 text-xs font-semibold">
                            <tr>
                                <th scope="col" class="h-9 px-3 text-left">Irban</th>
                                <th scope="col" class="h-9 px-3 text-right">Rencana</th>
                                <th scope="col" class="h-9 px-3 text-right">Realisasi</th>
                                <th scope="col" class="h-9 px-3 text-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($grafik['label'] as $i => $label)
                                <tr class="border-t border-neutral-200" wire:key="baris-{{ $i }}">
                                    <th scope="row" class="h-9 px-3 text-left font-normal">{{ $label }}</th>
                                    <td class="px-3 text-right">{{ $grafik['rencana'][$i] }}</td>
                                    <td class="px-3 text-right">{{ $grafik['realisasi'][$i] }}</td>
                                    <td class="px-3 text-right">{{ $persen($grafik['persen'][$i]) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>

    <nav aria-label="Tautan dasbor" class="mt-6 grid gap-3 sm:grid-cols-2">
        <x-ui.card :href="route('pimpinan.monitoring')" wire:navigate class="flex items-center gap-3">
            <span aria-hidden="true" class="shrink-0 rounded-md bg-mint-100 p-2">{{ svg('heroicon-o-table-cells', 'size-6 text-mint-800') }}</span>
            <span class="min-w-0">
                <span class="block text-base font-semibold text-mint-700">Monitoring SPT</span>
                <span class="block text-sm text-neutral-600">Realisasi SPT per item PKPT.</span>
            </span>
        </x-ui.card>
        <x-ui.card :href="route('pimpinan.rekap')" wire:navigate class="flex items-center gap-3">
            <span aria-hidden="true" class="shrink-0 rounded-md bg-mint-100 p-2">{{ svg('heroicon-o-chart-bar', 'size-6 text-mint-800') }}</span>
            <span class="min-w-0">
                <span class="block text-base font-semibold text-mint-700">Rekap Triwulanan</span>
                <span class="block text-sm text-neutral-600">Rencana dan realisasi SPT per triwulan.</span>
            </span>
        </x-ui.card>
    </nav>
</div>
