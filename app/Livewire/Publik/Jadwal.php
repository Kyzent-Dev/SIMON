<?php

namespace App\Livewire\Publik;

use App\Services\Publik\JadwalPublik;
use App\Services\Publik\RealisasiPublik;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Public Jadwal PKPT (design/public/jadwal.md). Holds only filter scalars; rows come from JadwalPublik in render() as plain
 * arrays, so nothing private reaches the snapshot. The Linimasa/Bulanan toggle, legend highlight and detail panel are client-side
 * (Alpine `jadwal`).
 */
class Jadwal extends Component
{
    #[Url]
    public string $tahun = '';

    /** '' = Semua Irban. */
    #[Url]
    public string $irban = '';

    /** Index of the irban tab in the Wilayah Pengawasan panel. */
    public string $wilayahTab = '0';

    public function render()
    {
        $realisasi = app(RealisasiPublik::class);
        $opsi = $realisasi->opsi();
        $tahun = in_array((int) $this->tahun, $opsi['tahun'], true) ? (int) $this->tahun : $opsi['tahunDefault'];
        $irbanId = collect($opsi['irban'])->firstWhere('id', (int) $this->irban)['id'] ?? null;
        $this->tahun = (string) $tahun;
        $this->irban = $irbanId ? (string) $irbanId : '';

        $data = ['item' => [], 'linimasa' => null, 'bulanan' => [], 'wilayah' => [], 'gagal' => false];
        try {
            $svc = app(JadwalPublik::class);
            $data['item'] = $svc->item($tahun, $irbanId);
            $data['linimasa'] = $svc->linimasa($data['item'], $tahun);
            $data['bulanan'] = $svc->bulanan($data['item'], $tahun);
            $data['wilayah'] = $svc->wilayah($tahun);
        } catch (Throwable $e) {
            report($e);
            $data['gagal'] = true;
        }
        if (! array_key_exists((int) $this->wilayahTab, $data['wilayah']) || ! ctype_digit($this->wilayahTab)) {
            $this->wilayahTab = '0';
        }

        return view('livewire.publik.jadwal', $data + [
            'tahunAngka' => $tahun,
            'opsi' => $opsi,
            'awal' => ! app('livewire')->isLivewireRequest(),
            'bulanIni' => now()->year === $tahun ? now()->month : 1,
        ])->layout('layouts.public')->title("Jadwal PKPT {$tahun}");
    }
}
