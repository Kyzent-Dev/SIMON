{{-- Rekap Triwulanan / Rekap Saya (pimpinan.md "Rekap Triwulanan", irban.md "Rekap Saya", _shared.md §3 tables + §6 filter bar/export).
     Compact tables with a bold Total row (neutral-300 top border), numbers right-aligned tabular. Sections come from PembangunRekap::bagian(), shared with the exports.
     No animation (DESIGN §7.5). --}}
<div>
    <x-ui.page-header :title="$judul" description="Rencana dan realisasi SPT per triwulan.">
        <x-slot:actions>
            <x-app.export-buttons />
        </x-slot:actions>
    </x-ui.page-header>

    <div class="mb-4 flex flex-wrap items-end gap-3">
        <div class="w-28"><x-ui.select label="Tahun" name="tahun" id="rekap-tahun" wire:model.live="tahun" :options="$tahunList" :value="$this->tahun" /></div>
        <div class="w-40"><x-ui.select label="Triwulan" name="tw" id="rekap-tw" wire:model.live="tw" :options="\App\Services\Ekspor\PembangunRekap::TW" placeholder="Semua TW" :value="$this->tw" /></div>
    </div>

    @unless ($rekap['pkpt_disahkan'])
        <x-ui.alert variant="warning" class="mb-4">PKPT {{ $rekap['tahun'] }} belum disahkan; rencana belum dapat dihitung.</x-ui.alert>
    @endunless

    @if ($pimpinan)
        <x-ui.tabs :tabs="['irban' => 'Per Irban', 'gabungan' => 'Gabungan']" :active="$this->tab" model="tab" label="Tampilan rekap" panel="rekap-panel" />
    @endif

    <div id="rekap-panel" @if ($pimpinan) role="tabpanel" aria-labelledby="rekap-panel-tab-{{ $this->tab }}" @endif
        wire:loading.delay.class="opacity-60" wire:loading.delay.attr="aria-busy" wire:target="tahun,tw,tab,irban"
        class="space-y-8">
        @foreach ($bagian as $i => $s)
            <section wire:key="bagian-{{ $i }}-{{ $this->tab }}" aria-labelledby="rekap-bagian-{{ $i }}">
                <h2 id="rekap-bagian-{{ $i }}" class="mb-3 font-display text-lg font-semibold text-neutral-900">{{ $s['judul'] }}</h2>
                <div class="grid gap-4 {{ count($s['tabel']) > 1 ? 'xl:grid-cols-2' : '' }}">
                    @foreach ($s['tabel'] as $t)
                        <div wire:key="tabel-{{ $i }}-{{ $loop->index }}" class="min-w-0">
                            @if ($t['judul'])
                                <h3 class="mb-2 text-sm font-semibold text-neutral-900">{{ $t['judul'] }}</h3>
                            @endif
                            <x-ui.table density="compact" label="{{ $s['judul'] }}{{ $t['judul'] ? ' '.$t['judul'] : '' }}">
                                <x-slot:head>
                                    <x-ui.th>{{ $i === 0 ? 'Triwulan' : ($i === 1 ? 'Klasifikasi' : 'Jenis Penugasan') }}</x-ui.th>
                                    @foreach ($s['kolom'] as $k)
                                        <x-ui.th numeric>{{ $k }}</x-ui.th>
                                    @endforeach
                                </x-slot:head>
                                @foreach ($t['baris'] as $b)
                                    <tr @class(['font-bold border-t-neutral-300!' => $b['total']])>
                                        <th scope="row" class="bg-white px-4 text-left text-sm {{ $b['total'] ? 'font-bold' : 'font-medium' }}">{{ $i === 0 ? $b['periode'] : $b['label'] }}</th>
                                        @foreach ($b['sel'] as $n)
                                            <td class="text-right">{{ is_int($n) ? number_format($n, 0, ',', '.') : $n }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </x-ui.table>
                        </div>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>
</div>
