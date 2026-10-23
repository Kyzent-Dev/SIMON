<?php

namespace App\Services\Ekspor;

use App\Enums\Role;
use App\Exports\RekapSheet;
use App\Exports\RekapWorkbook;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\User;
use App\Services\RealisasiItemPkpt;
use App\Services\RekapTriwulan;
use App\Support\Tanggal;
use Illuminate\Support\Facades\Gate;

/**
 * Rekap Triwulanan export. The sections come from bagian(), the same method the page renders, so the file equals the screen.
 * The user picks the sections (filter `bagian`, codes BAGIAN). xlsx: sheet Gabungan + one sheet per irban (RekapWorkbook, spec 9.2 §2.2).
 */
class PembangunRekap implements Pembangun
{
    public const TW = [1 => 'TW I', 2 => 'TW II', 3 => 'TW III', 4 => 'TW IV'];

    /** Section codes in canonical order: Rencana vs Realisasi, Per Klasifikasi, Per Jenis Penugasan, Detail Realisasi. */
    public const BAGIAN = ['rr', 'klasifikasi', 'jenis', 'detail'];

    /** Preselected sections per Rekap tab. */
    public const BAGIAN_DEFAULT = ['irban' => ['rr', 'klasifikasi', 'jenis'], 'gabungan' => ['rr', 'klasifikasi', 'jenis'], 'detail' => ['detail']];

    /** First column header of each summary section in the workbook. */
    private const KEPALA = ['rr' => 'Irban', 'klasifikasi' => 'Klasifikasi', 'jenis' => 'Jenis Penugasan'];

    private const RR = [['Rencana', 'Realisasi', '%'], ['rencana', 'realisasi', 'persen']];

    private const KL = [['Jumlah', 'Berjalan', 'Selesai'], ['jumlah', 'berjalan', 'selesai']];

    private const JN = [['Rencana', 'Realisasi', '%', 'Jumlah SPT'], ['rencana', 'realisasi', 'persen', 'jumlah']];

    private const DETAIL = 'Detail Realisasi per Item PKPT';

    private const KOLOM_DETAIL = ['Jenis Penugasan', 'Periode Penugasan (PKPT)', 'Nomor SPT', 'Realisasi Periode', 'Kesesuaian dengan Jadwal PKPT', 'Status', 'Permasalahan/Kendala'];

    public function __construct(private RekapTriwulan $rekap, private RealisasiItemPkpt $detailRealisasi) {}

    /** Known codes only, unique, in canonical order; nothing left → the default of tab `irban`. */
    public static function bagianTerpilih(mixed $nilai): array
    {
        return array_values(array_intersect(self::BAGIAN, array_filter((array) $nilai, 'is_string'))) ?: self::BAGIAN_DEFAULT['irban'];
    }

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
            'bagian' => self::bagianTerpilih($filter['bagian'] ?? null),
        ];
    }

    /**
     * Upper bound of the rows: selected summary sections (title + header + data rows of the scope's tables) × number of sheets,
     * plus the Detail Realisasi rows when selected (each detail row lands on one irban sheet only).
     */
    public function jumlah(User $user, array $filter): int
    {
        $ringkas = 0;
        if (array_diff($filter['bagian'], ['detail'])) {
            $data = $this->rekap->hitung($filter['tahun'], $filter['irban']);
            foreach ($this->bagian($data, $filter['tw'], $filter['irban'] ? 'irban' : 'gabungan') as $s) {
                $ringkas += in_array($s['kode'], $filter['bagian'], true) ? 2 + count($s['tabel'][0]['baris']) : 0;
            }
            $ringkas *= $filter['irban'] ? 1 : Irban::count() + 1;
        }

        return $ringkas + (in_array('detail', $filter['bagian'], true) ? array_sum(array_map(fn ($g) => count($g['baris']), $this->detail($filter))) : 0);
    }

    public function namaFile(User $user, array $filter): string
    {
        $kode = $filter['irban'] ? Irban::whereKey($filter['irban'])->value('kode_nomor') : null;

        return "rekap-triwulan-{$filter['tahun']}".($kode ? "-irban-{$kode}" : '').($filter['bagian'] === self::BAGIAN ? '' : '-'.implode('-', $filter['bagian']));
    }

    /** Sheets: Gabungan (summary sections only; left out when only `detail` is picked) then one per irban, or only the scoped irban. */
    public function export(User $user, array $filter): RekapWorkbook
    {
        $judul = 'Rekap Triwulanan '.$filter['tahun'].($filter['tw'] ? ' · '.self::TW[$filter['tw']] : '');
        $dibuat = now('Asia/Jakarta')->translatedFormat('d F Y H.i').' WIB';
        $sheets = [];
        if (! $filter['irban'] && array_diff($filter['bagian'], ['detail'])) {
            $sheets[] = new RekapSheet($judul, 'Gabungan', $dibuat, $this->bagianSheet($this->rekap->hitung($filter['tahun']), $filter, true));
        }
        $detail = in_array('detail', $filter['bagian'], true) ? array_column($this->detail($filter), null, 'id') : [];
        foreach (Irban::orderBy('id')->when($filter['irban'], fn ($q, $id) => $q->whereKey($id))->get(['id', 'nama']) as $i) {
            $bagian = $this->bagianSheet($this->rekap->hitung($filter['tahun'], $i->id), $filter, false);
            if (isset($detail[$i->id])) {
                $bagian[] = ['judul' => self::DETAIL, 'kolom' => self::KOLOM_DETAIL, 'tipe' => ['label', 'tanggal', 'nomor', 'tanggal', 'teks', 'teks', 'panjang'],
                    'baris' => $detail[$i->id]['baris'], 'total' => [], 'gabung' => $detail[$i->id]['gabung']];
            }
            $sheets[] = new RekapSheet($judul, $i->nama, $dibuat, $bagian);
        }

        return new RekapWorkbook($sheets);
    }

    /** Selected summary sections of one sheet in RekapSheet shape; the Gabungan sheet adds "Ringkasan per Irban" after Rencana vs Realisasi. */
    private function bagianSheet(array $data, array $filter, bool $gabungan): array
    {
        $hasil = [];
        foreach ($this->bagian($data, $filter['tw'], $gabungan ? 'gabungan' : 'irban') as $s) {
            if (! in_array($s['kode'], $filter['bagian'], true)) {
                continue;
            }
            $baris = $s['tabel'][0]['baris'];
            $hasil[] = ['judul' => $s['judul'], 'kolom' => [self::KEPALA[$s['kode']], 'Periode', ...$s['kolom']], 'tipe' => ['label', 'teks', ...array_fill(0, count($s['kolom']), 'angka')],
                'baris' => array_map(fn ($b) => [$b['label'], $b['periode'], ...$b['sel']], $baris), 'total' => array_keys(array_filter(array_column($baris, 'total')))];
            if ($s['kode'] === 'rr' && $gabungan) {
                $sel = fn (array $b) => $filter['tw'] ? $b['tw'][$filter['tw']] : $b['total'];
                $hasil[] = ['judul' => 'Ringkasan per Irban', 'kolom' => ['Irban', ...self::RR[0]], 'tipe' => ['label', 'angka', 'angka', 'angka'], 'total' => [],
                    'baris' => array_map(fn ($b) => [$b['nama'], ...array_map(fn ($k) => $sel($b)[$k] ?? '–', self::RR[1])], $data['irban']['baris'])];
            }
        }

        return $hasil;
    }

    public function html(User $user, array $filter): string
    {
        $data = $this->rekap->hitung($filter['tahun'], $filter['irban']);
        $pilihan = $filter['bagian'];

        return view('pdf.rekap-triwulan', [
            'tahun' => $filter['tahun'], 'tw' => $filter['tw'] ? self::TW[$filter['tw']] : 'Semua TW',
            'irban' => $filter['irban'] ? Irban::whereKey($filter['irban'])->value('nama') : null,
            'pkptDisahkan' => $data['pkpt_disahkan'], 'detailJudul' => self::DETAIL, 'detailKolom' => self::KOLOM_DETAIL,
            'detail' => in_array('detail', $pilihan, true) ? $this->detail($filter) : [], 'pilihan' => $pilihan,
            'bagian' => array_values(array_filter($this->bagian($data, $filter['tw'], 'semua'), fn ($s) => in_array($s['kode'], $pilihan, true))),
            'dibuat' => now('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB',
        ])->render();
    }

    /** Failure notification target: Irban accounts have their own rekap page. */
    public function halaman(User $user): string
    {
        return route($user->role === Role::Irban ? 'irban.rekap' : 'pimpinan.rekap', absolute: false);
    }

    /**
     * Fourth section, same service as the Detail Realisasi tab. One table per irban; Jenis and Periode only on the first row of an item,
     * `gabung` = the row span of each item (merged in the xlsx).
     *
     * @return list<array{id: int, irban: string, baris: list<list<string>>, gabung: list<array{0: int, 1: int}>}>
     */
    private function detail(array $filter): array
    {
        $hasil = [];
        foreach ($this->detailRealisasi->untuk($filter['tahun'], $filter['tw'], $filter['irban']) as $g) {
            $baris = $gabung = [];
            foreach ($g['item'] as $it) {
                $mulai = count($baris);
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
                $gabung[] = [$mulai, count($baris) - 1];
            }
            $hasil[] = ['id' => $g['irban']['id'], 'irban' => $g['irban']['nama'], 'baris' => $baris ?: [['Tidak ada item PKPT pada periode ini.', '', '', '', '', '', '']], 'gabung' => $gabung];
        }

        return $hasil;
    }

    /**
     * Sections shown on the page and in the files. $tab: 'irban' (one Rencana vs Realisasi table per irban), 'gabungan' (summed), 'semua' (both, for export).
     * Rencana vs Realisasi rows are TW I-IV + Total (one row when $tw is set); Klasifikasi and Jenis rows are the names, valued per $tw or the year total.
     *
     * @return list<array{kode: string, judul: string, kolom: list<string>, tabel: list<array{judul: ?string, baris: list<array{label: string, periode: string, sel: list<int|string>, total: bool}>}>}>
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
            ['kode' => 'rr', 'judul' => 'Rencana vs Realisasi', 'kolom' => self::RR[0], 'tabel' => $tabel],
            ['kode' => 'klasifikasi', 'judul' => 'Per Klasifikasi', 'kolom' => self::KL[0], 'tabel' => [['judul' => null, 'baris' => $this->barisNama($rekap['klasifikasi'], self::KL[1], $tw)]]],
            ['kode' => 'jenis', 'judul' => 'Per Jenis Penugasan', 'kolom' => self::JN[0], 'tabel' => [['judul' => null, 'baris' => $this->barisNama($rekap['jenis'], self::JN[1], $tw)]]],
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
