<?php

namespace App\Livewire\Publik;

use App\Services\Publik\RealisasiPublik;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Public Realisasi SPT (design/public/realisasi.md). Holds only filter scalars; every number comes from RealisasiPublik in render()
 * as plain arrays, so nothing private reaches the snapshot. Each aggregate is caught on its own: a failure blanks only its card.
 * Charts get their data as JSON in the first render; later renders dispatch `grafik-publik` and the charts update (200ms).
 */
class Realisasi extends Component
{
    use WithPagination;

    private const FILTER = ['tahun', 'tw', 'bulan', 'irban', 'wilayah', 'jenis'];

    #[Url]
    public string $tahun = '';

    #[Url]
    public string $tw = '';

    #[Url]
    public string $bulan = '';

    #[Url]
    public string $irban = '';

    /** Nullable: the combobox clears to null. */
    #[Url]
    public ?string $wilayah = '';

    #[Url]
    public string $jenis = '';

    #[Url]
    public string $urut = 'desc';

    public bool $perIrban = false;

    /** irban|jenis */
    public string $proporsi = 'irban';

    /** TW of the bar card: '1'–'4', '0' = Setahun, '' = follow the TW filter. */
    public string $twGrafik = '';

    /** Set during this request when a chart input changed (filters, card controls); page and sort changes leave the charts alone. */
    private bool $grafikBerubah = false;

    public function updated(string $nama): void
    {
        $this->grafikBerubah = true;
        if (in_array($nama, self::FILTER, true)) {
            $this->resetPage();
        }
        if ($nama === 'tw') {
            $this->twGrafik = '';
        }
    }

    public function hapus(string $filter): void
    {
        if (in_array($filter, ['tw', 'bulan', 'irban', 'wilayah', 'jenis'], true)) {
            $this->{$filter} = '';
            $this->updated($filter);
        }
        $this->dispatch('fokus-filter');
    }

    public function aturUlang(): void
    {
        $this->reset([...self::FILTER, 'twGrafik']);
        $this->resetPage();
        $this->grafikBerubah = true;
        $this->dispatch('fokus-filter');
    }

    public function urutkan(): void
    {
        $this->urut = $this->urut === 'asc' ? 'desc' : 'asc';
        $this->resetPage();
    }

    public function render()
    {
        $svc = app(RealisasiPublik::class);
        $opsi = $svc->opsi();
        $this->klem($opsi);
        $f = ['tahun' => (int) $this->tahun, 'tw' => $this->angka($this->tw), 'bulan' => $this->angka($this->bulan),
            'irban' => $this->angka($this->irban), 'wilayah' => $this->angka($this->wilayah), 'jenis' => $this->angka($this->jenis)];
        $twGrafik = $this->twGrafik !== '' ? (int) $this->twGrafik : ($f['tw'] ?? 0);
        // The card's TW switch is a radio group bound to this property: it must hold the effective value, or no segment shows as selected ('' = follow the TW filter, reset in updated()).
        $this->twGrafik = (string) $twGrafik;

        $data = [
            'kpi' => $this->aman(fn () => $svc->ringkasan($f)),
            'garis' => $this->aman(fn () => $this->garis($svc, $f, $opsi)),
            'donat' => $this->aman(fn () => ['irisan' => $svc->proporsi($f, $this->proporsi)]),
            'batang' => $this->aman(fn () => $svc->rencanaVsRealisasi($f, $twGrafik ?: null)),
            'tabel' => $this->aman(fn () => $this->tabel($svc, $f)),
        ];
        $awal = ! app('livewire')->isLivewireRequest();
        if (! $awal && $this->grafikBerubah) {
            $this->dispatch('grafik-publik', garis: $data['garis'], donat: $data['donat'], batang: $data['batang']);
        }

        return view('livewire.publik.realisasi', $data + [
            'opsi' => $opsi,
            'awal' => $awal,
            'twGrafikEfektif' => (string) $twGrafik,
        ])->layout('layouts.public')->title("Realisasi Pengawasan {$this->tahun}");
    }

    /** Unknown or out-of-range URL values fall back to the defaults; a bulan outside the chosen TW is dropped. */
    private function klem(array $opsi): void
    {
        $ada = fn (string $nilai, array $daftar) => in_array((int) $nilai, array_column($daftar, 'id'), true) && ctype_digit($nilai);
        $this->tahun = (string) (in_array((int) $this->tahun, $opsi['tahun'], true) ? (int) $this->tahun : $opsi['tahunDefault']);
        $this->tw = in_array($this->tw, ['1', '2', '3', '4'], true) ? $this->tw : '';
        $bulan = ctype_digit($this->bulan) ? (int) $this->bulan : 0;
        $this->bulan = $bulan >= 1 && $bulan <= 12 && ($this->tw === '' || intdiv($bulan - 1, 3) + 1 === (int) $this->tw) ? (string) $bulan : '';
        $this->irban = $ada($this->irban, $opsi['irban']) ? $this->irban : '';
        $this->wilayah = $ada((string) $this->wilayah, $opsi['wilayah']) ? (string) $this->wilayah : '';
        $this->jenis = $ada($this->jenis, $opsi['jenis']) ? $this->jenis : '';
        $this->urut = $this->urut === 'asc' ? 'asc' : 'desc';
        $this->proporsi = $this->proporsi === 'jenis' ? 'jenis' : 'irban';
        $this->twGrafik = in_array($this->twGrafik, ['0', '1', '2', '3', '4'], true) ? $this->twGrafik : '';
    }

    private function angka(?string $nilai): ?int
    {
        return $nilai === null || $nilai === '' ? null : (int) $nilai;
    }

    private function aman(callable $hitung): mixed
    {
        try {
            return $hitung();
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Line chart data: one mint series, or one per irban with its colour index (irban order by name, as proporsi). */
    private function garis(RealisasiPublik $svc, array $f, array $opsi): array
    {
        $bulan = array_map(fn ($b) => CarbonImmutable::create(2000, $b, 1)->translatedFormat('M'), range(1, 12));
        if (! $this->perIrban) {
            return ['bulan' => $bulan, 'seri' => [['nama' => 'SPT selesai', 'data' => $svc->perBulan($f, false), 'warna' => null]]];
        }
        $nama = array_column($opsi['irban'], 'nama');

        return ['bulan' => $bulan, 'seri' => array_map(
            fn ($s) => ['nama' => $s['irban'], 'data' => $s['jumlah'], 'warna' => array_search($s['irban'], $nama, true)],
            $svc->perBulan($f, true),
        )];
    }

    private function tabel(RealisasiPublik $svc, array $f)
    {
        $halaman = $this->getPage();
        $hasil = $svc->tabel($f, $this->urut, $halaman);
        if ($hasil->isEmpty() && $halaman > 1 && $hasil->total() > 0) {
            $this->setPage($hasil->lastPage());
            $hasil = $svc->tabel($f, $this->urut, $hasil->lastPage());
        }

        return $hasil;
    }
}
