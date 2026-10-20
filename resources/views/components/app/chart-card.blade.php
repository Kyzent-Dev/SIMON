{{-- DESIGN §8 x-app.chart-card (line, donut, bar): title (h2), filter summary, the chart area as `role="img"` + `aria-label` summary sentence,
     and a "Lihat sebagai tabel" / "Sembunyikan tabel" toggle revealing a real <table> (slot `tabel`) that is always in the DOM (DESIGN §9).
     The default slot holds the chart element (`wire:ignore` + Alpine chart component + <canvas x-ref="kanvas" aria-hidden="true">).
     Slots: `aksi` (controls beside the title), `keterangan` (caption under the header), `legenda` (text legend under the chart).
     `gagal`: an inline danger alert replaces chart and table, so one failing aggregate never blanks the page (realisasi.md "States").
     `muat`: wire:target list that shows a chart skeleton (wire:loading.delay, 200ms). The table twin has no static `sr-only`: without JS
     the canvas cannot draw, so the table is the content; Alpine hides it at init. `relative` keeps sr-only from widening the page. --}}
@props(['id', 'judul', 'ringkasan' => null, 'label' => '', 'gagal' => false, 'muat' => null, 'tinggi' => 'h-72'])

<section aria-labelledby="{{ $id }}-judul" {{ $attributes->class('min-w-0 rounded-lg border border-neutral-200 bg-white p-4 shadow-xs lg:p-5') }}>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 id="{{ $id }}-judul" class="font-display text-[22px] leading-[30px] font-bold text-neutral-900">{{ $judul }}</h2>
            @if ($ringkasan)
                <p class="mt-1 text-sm text-neutral-600">{{ $ringkasan }}</p>
            @endif
        </div>
        {{ $aksi ?? '' }}
    </div>
    {{ $keterangan ?? '' }}

    @if ($gagal)
        <x-ui.alert variant="danger" class="mt-4">Grafik gagal dimuat.</x-ui.alert>
    @else
        <div role="img" aria-label="{{ $label }}" class="relative mt-4 {{ $tinggi }}">
            {{ $slot }}
            @if ($muat)
                <div wire:loading.delay wire:target="{{ $muat }}" aria-hidden="true" class="absolute inset-0 z-[1] bg-white">
                    <div class="flex h-full items-end gap-3 border-b border-l border-neutral-300 px-3 pt-6">
                        @foreach ([45, 70, 55, 85, 60, 35] as $h)
                            <div class="flex-1 animate-shimmer rounded-t-sm bg-[linear-gradient(90deg,var(--color-neutral-100),var(--color-neutral-50),var(--color-neutral-100))] bg-[length:200%_100%]" style="height: {{ $h }}%"></div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
        {{ $legenda ?? '' }}
        <div x-data="{ tabel: false }" class="mt-3">
            <button type="button" x-cloak x-on:click="tabel = ! tabel" aria-expanded="false" x-bind:aria-expanded="tabel.toString()" aria-controls="{{ $id }}-tabel"
                class="inline-flex min-h-11 items-center gap-1 rounded-sm text-sm font-medium text-mint-700 hover:underline lg:min-h-8">
                <span x-text="tabel ? 'Sembunyikan tabel' : 'Lihat sebagai tabel'">Lihat sebagai tabel</span>
                <span x-bind:class="tabel && 'rotate-180'">{{ svg('heroicon-m-chevron-down', 'size-4', ['aria-hidden' => 'true']) }}</span>
            </button>
            <div id="{{ $id }}-tabel" x-bind:class="tabel ? 'mt-2 overflow-x-auto' : 'sr-only'" class="relative">
                <table class="w-full border-collapse text-sm text-neutral-900 tabular-nums">
                    <caption class="sr-only">{{ $judul }}{{ $ringkasan ? ', '.$ringkasan : '' }}</caption>
                    {{ $tabel }}
                </table>
            </div>
        </div>
    @endif
</section>
