<?php

namespace App\Services\Ekspor;

use App\Enums\Role;
use App\Exports\ArrayExport;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\User;
use App\Services\RealisasiItemPkpt;
use App\Services\RekapTriwulan;
use App\Support\Tanggal;
use Illuminate\Support\Facades\Gate;

/**
 * Rekap Triwulanan export. The sections come from bagian(), the same method the page renders, so the file equals the screen.
 * ArrayExport is single-sheet: the four sections follow each other in one sheet, each with a title row and a column-header row.
 */
class PembangunRekap implements Pembangun
{
    public const TW = [1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'];

    private const RR = [['Rencana', 'Realisasi', '%'], ['rencana', 'realisasi', 'persen']];

    private const KL = [['Jumlah', 'Berjalan', 'Selesai'], ['jumlah', 'berjalan', 'selesai']];

    private const JN = [['Rencana', 'Realisasi', '%', 'Jumlah SPT'], ['rencana', 'realisasi', 'persen', 'jumlah']];

    private const DETAIL = 'Detail Realisasi per Item PKPT';

    private const KOLOM_DETAIL = ['Jenis Penugasan', 'Periode Penugasan (PKPT)', 'Nomor SPT', 'Realisasi Periode', 'Kesesuaian dengan Jadwal PKPT', 'Status', 'Permasalahan/Kendala'];

    public function __construct(private RekapTriwulan $rekap, private RealisasiItemPkpt $detailRealisasi) {}

    public function otorisasi(User $user): void
    {
        Gate::forUser($user)->authorize('rekap', Spt::class);
    }

    /** Irban accounts are always scoped to their own irban; a tampered `irban` is ignored. */
    public function filter(User $user, array $filter): array
    {
        $tahun = (int) ($filter['tahun'] ?? 0);
        $tw = (int) ($filter['tw'] ?? 0);
        $irban = $user->role === Role::Irban ? $user->irban_id : Irban::whereKey((int) ($filter['irban'] ?? 0))->value('id');

        return [
            'tahun' => $tahun >= 2000 && $tahun <= 2100 ? $tahun : (int) now('Asia/Jakarta')->year,
            'tw' => $tw >= 1 && $tw <= 4 ? $tw : 0,
            'irban' => $irban ? (int) $irban : null,
        ];
    }

    /** The three summary tables are small; the Detail Realisasi rows decide whether the export is queued. */
    public function jumlah(User $user, array $filter): int
    {
        return array_sum(array_map(fn ($g) => count($g['baris']), $this->detail($filter)));
    }

    public function namaFile(User $user, array $filter): string
    {
        $kode = $filter['irban'] ? Irban::whereKey($filter['irban'])->value('kode_nomor') : null;

        return "rekap-triwulan-{$filter['tahun']}".($kode ? "-irban-{$kode}" : '');
    }

    public function export(User $user, array $filter): ArrayExport
    {
        $data = $this->rekap->hitung($filter['tahun'], $filter['irban']);
        $rows = [];
        foreach ($this->bagian($data, $filter['tw'], 'semua') as $s) {
            $rows[] = [$s['judul']];
            foreach ($s['tabel'] as $t) {
                $rows[] = [$t['judul'] ?? '', 'Periode', ...$s['kolom']];
                foreach ($t['baris'] as $b) {
                    $rows[] = [$b['label'], $b['periode'], ...$b['sel']];
                }
            }
            $rows[] = [];
        }
        $rows[] = [self::DETAIL];
        foreach ($this->detail($filter) as $g) {
            $rows[] = [$g['irban']];
            $rows[] = self::KOLOM_DETAIL;
            array_push($rows, ...$g['baris']);
            $rows[] = [];
        }

        return new ArrayExport(['Rekap Triwulanan '.$filter['tahun'].($filter['tw'] ? ' '.self::TW[$filter['tw']] : '')], $rows);
    }

    public function html(User $user, array $filter): string
    {
        $data = $this->rekap->hitung($filter['tahun'], $filter['irban']);

        return view('pdf.rekap-triwulan', [
            'tahun' => $filter['tahun'], 'tw' => $filter['tw'] ? self::TW[$filter['tw']] : 'Semua TW',
            'irban' => $filter['irban'] ? Irban::whereKey($filter['irban'])->value('nama') : null,
            'pkptDisahkan' => $data['pkpt_disahkan'], 'detailJudul' => self::DETAIL, 'detailKolom' => self::KOLOM_DETAIL, 'detail' => $this->detail($filter),
            'bagian' => $this->bagian($data, $filter['tw'], 'semua'),
            'dibuat' => now('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB',
        ])->render();
    }

    /** Failure notification target: Irban accounts have their own rekap page. */
    public function halaman(User $user): string
    {
        return route($user->role === Role::Irban ? 'irban.rekap' : 'pimpinan.rekap', absolute: false);
    }

    /**
     * Fourth section, same service as the Detail Realisasi tab. One table per irban; Jenis and Periode only on the first row of an item
     * (shortcut: no merged cells, ArrayExport cannot merge).
     *
     * @return list<array{irban: string, baris: list<list<string>>}>
     */
    private function detail(array $filter): array
    {
        $hasil = [];
        foreach ($this->detailRealisasi->untuk($filter['tahun'], $filter['tw'], $filter['irban']) as $g) {
            $baris = [];
            foreach ($g['item'] as $it) {
                $awal = [$it['jenis']['nama'], Tanggal::rentang($it['mulai'], $it['selesai'])];
                foreach ($it['spt'] ?: [null] as $r) {
                    $baris[] = [...($awal ?? ['', '']), ...($r ? [
                        $r['nomor_lengkap'], Tanggal::rentang($r['mulai'], $r['selesai']),
                        $r['kesesuaian'] === 'sesuai' ? 'Sesuai' : ($r['kesesuaian'] === 'tidak_sesuai' ? 'Tidak Sesuai PKPT' : 'Non-PKPT'),
                        $r['status']->label().(in_array('terlambat', $r['penanda'], true) ? ' · Terlambat' : ''),
                        $r['kendala'] ? $r['kendala']['kategori'].': '.$r['kendala']['uraian'] : '–',
                    ] : ['–', 'Belum ada SPT', '–', '–', '–'])];
                    $awal = null;
                }
            }
            $hasil[] = ['irban' => $g['irban']['nama'], 'baris' => $baris ?: [['Tidak ada item PKPT pada periode ini.', '', '', '', '', '', '']]];
        }

        return $hasil;
    }

    /**
     * Sections shown on the page and in the files. $tab: 'irban' (one Rencana vs Realisasi table per irban), 'gabungan' (summed), 'semua' (both, for export).
     * Rencana vs Realisasi rows are TW I-IV + Total (one row when $tw is set); Klasifikasi and Jenis rows are the names, valued per $tw or the year total.
     *
     * @return list<array{judul: string, kolom: list<string>, tabel: list<array{judul: ?string, baris: list<array{label: string, periode: string, sel: list<int|string>, total: bool}>}>}>
     */
    public function bagian(array $rekap, int $tw, string $tab): array
    {
        $irban = $rekap['irban'];
        $tabel = [];
        if ($tab !== 'gabungan') {
            foreach ($irban['baris'] as $b) {
                $tabel[] = ['judul' => $b['nama'], 'baris' => $this->barisTw($b, self::RR[1], $tw)];
            }
        }
        if ($tab === 'gabungan' || ($tab === 'semua' && count($irban['baris']) > 1)) {
            $tabel[] = ['judul' => $tab === 'gabungan' ? null : 'Gabungan', 'baris' => $this->barisTw($irban['total'], self::RR[1], $tw)];
        }

        return [
            ['judul' => 'Rencana vs Realisasi', 'kolom' => self::RR[0], 'tabel' => $tabel],
            ['judul' => 'Per Klasifikasi', 'kolom' => self::KL[0], 'tabel' => [['judul' => null, 'baris' => $this->barisNama($rekap['klasifikasi'], self::KL[1], $tw)]]],
            ['judul' => 'Per Jenis Penugasan', 'kolom' => self::JN[0], 'tabel' => [['judul' => null, 'baris' => $this->barisNama($rekap['jenis'], self::JN[1], $tw)]]],
        ];
    }

    private function barisTw(array $b, array $kunci, int $tw): array
    {
        $nama = $b['nama'] ?? 'Gabungan';
        $baris = [];
        foreach ($tw ? [$tw] : array_keys(self::TW) as $n) {
            $baris[] = $this->baris($nama, self::TW[$n], $b['tw'][$n], $kunci, false);
        }
        if (! $tw) {
            $baris[] = $this->baris($nama, 'Total', $b['total'], $kunci, true);
        }

        return $baris;
    }

    private function barisNama(array $tabel, array $kunci, int $tw): array
    {
        $ambil = fn (array $b) => $tw ? $b['tw'][$tw] : $b['total'];
        $periode = $tw ? self::TW[$tw] : 'Setahun';
        $baris = array_map(fn ($b) => $this->baris($b['nama'], $periode, $ambil($b), $kunci, false), $tabel['baris']);
        $baris[] = $this->baris('Total', $periode, $ambil($tabel['total']), $kunci, true);

        return $baris;
    }

    private function baris(string $label, string $periode, array $sel, array $kunci, bool $total): array
    {
        return ['label' => $label, 'periode' => $periode, 'total' => $total, 'sel' => array_map(fn ($k) => $sel[$k] ?? '–', $kunci)];
    }
}
