<?php

namespace App\Livewire\Pimpinan;

use App\Enums\StatusPengajuan;
use App\Enums\StatusRevisi;
use App\Models\Komentar;
use App\Models\PkptItemRevisi;
use App\Models\Spt;
use App\Services\Ekspor\PembangunRekap;
use App\Services\MonitoringSpt;
use App\Services\RekapTriwulan;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Dasbor Pimpinan (pimpinan.md): six stat cards, "Realisasi vs Rencana per Irban" chart with table twin, up to 5 pending schedule revisions.
 * Read-only. Card counts come from MonitoringSpt::jumlah() with the same filter as the card's link, so the number equals the monitoring rows.
 */
#[Title('Dasbor Pimpinan')]
class Dasbor extends Component
{
    #[Url]
    public string $tahun = '';

    /** Chart quarter: '' = Setahun, else 1-4. */
    #[Url]
    public string $tw = '';

    /** Set by a filter change in this request: cards render without count-up and the chart gets its new data by event. */
    private bool $berubah = false;

    public function mount(): void
    {
        Gate::authorize('monitoring', Spt::class);
    }

    public function updated(): void
    {
        Gate::authorize('monitoring', Spt::class);
        $this->berubah = true;
    }

    public function render()
    {
        Gate::authorize('monitoring', Spt::class);
        $monitoring = app(MonitoringSpt::class);
        $tahun = $monitoring->filter(['tahun' => $this->tahun])['tahun'];
        $tw = in_array($this->tw, ['1', '2', '3', '4'], true) ? (int) $this->tw : null;
        $this->tahun = (string) $tahun;
        $this->tw = (string) $tw;

        $rekap = app(RekapTriwulan::class)->hitung($tahun);
        $grafik = $this->grafik($rekap, $tw);
        if ($this->berubah) {
            $this->dispatch('grafik-realisasi', data: $grafik);
        }

        $menunggu = PkptItemRevisi::where('status', StatusRevisi::Menunggu);
        $tahunList = Spt::where('status_pengajuan', StatusPengajuan::Diterima)->whereNotNull('tahun')->distinct()->pluck('tahun')
            ->push((int) now('Asia/Jakarta')->year, $tahun)->unique()->sortDesc()->mapWithKeys(fn ($t) => [$t => (string) $t])->all();

        return view('livewire.pimpinan.dasbor', [
            'kartu' => $this->kartu($monitoring, $tahun, (clone $menunggu)->count()),
            'animasi' => ! $this->berubah,
            'grafik' => $grafik,
            'pkptDisahkan' => $rekap['pkpt_disahkan'],
            'revisi' => $menunggu->with(['item.irban', 'item.jenisPenugasan', 'item.pkpt', 'diinputOleh', 'diajukanOleh'])
                ->orderBy('created_at')->orderBy('id')->limit(5)->get(),
            'tahunList' => $tahunList,
        ]);
    }

    /** @return list<array{label: string, nilai: int, href: string, alert: bool}> */
    private function kartu(MonitoringSpt $monitoring, int $tahun, int $revisi): array
    {
        $kartu = [];
        foreach ([
            'Berjalan' => ['status' => 'berjalan'],
            'Selesai' => ['status' => 'selesai'],
            'Terlambat' => ['penanda' => 'terlambat'],
            'Tidak Sesuai PKPT' => ['kesesuaian' => 'tidak_sesuai'],
        ] as $label => $filter) {
            $filter = ['tahun' => $tahun, ...$filter];
            $kartu[] = ['label' => $label, 'nilai' => $monitoring->jumlah($filter), 'href' => route('pimpinan.monitoring', $filter), 'alert' => $label === 'Terlambat'];
        }
        // All years (open requests are work regardless of year); the link opens the monitoring rows of the selected year.
        $kartu[] = ['label' => 'Permintaan Penjelasan Terbuka', 'nilai' => Komentar::permintaanTerbuka()->count(),
            'href' => route('pimpinan.monitoring', ['tahun' => $tahun, 'penjelasan' => 'terbuka']), 'alert' => false];
        $kartu[] = ['label' => 'Revisi Jadwal Menunggu', 'nilai' => $revisi, 'href' => route('pimpinan.revisi-jadwal'), 'alert' => $revisi > 0];

        return $kartu;
    }

    /** @return array{periode: string, label: list<string>, rencana: list<int>, realisasi: list<int>, persen: list<?int>} */
    private function grafik(array $rekap, ?int $tw): array
    {
        $sel = array_map(fn ($r) => $tw ? $r['tw'][$tw] : $r['total'], $rekap['irban']['baris']);

        return [
            'periode' => $tw ? PembangunRekap::TW[$tw] : 'Setahun',
            'label' => array_column($rekap['irban']['baris'], 'nama'),
            'rencana' => array_column($sel, 'rencana'),
            'realisasi' => array_column($sel, 'realisasi'),
            'persen' => array_column($sel, 'persen'),
        ];
    }
}
