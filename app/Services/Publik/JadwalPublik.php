<?php

namespace App\Services\Publik;

use App\Enums\StatusPengajuan;
use App\Services\KesesuaianPkpt;
use App\Services\WilayahPengawasan;
use App\Support\MingguPkpt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Public Jadwal PKPT (PRD §7.2): items of the year's Disahkan PKPT as plain arrays, plus timeline/monthly layouts. */
class JadwalPublik
{
    public const BELUM_DITENTUKAN = 'Belum ditentukan';

    public function __construct(private RealisasiPublik $realisasi, private KesesuaianPkpt $kesesuaian) {}

    /**
     * @return list<array{id: int, jenis: array, irban: string, mulai: CarbonImmutable, selesai: CarbonImmutable,
     *   semula: ?array{0: CarbonImmutable, 1: CarbonImmutable}, tambahan_ke: ?int, wilayah: string, selesaiSpt: int, target: int}>
     *   ordered by jenis name, effective start, id
     */
    public function item(int $tahun, ?int $irban): array
    {
        $item = $this->realisasi->itemDisahkan($tahun, $irban);
        if ($item->isEmpty()) {
            return [];
        }
        // Only the package number reaches the public page ("Tambahan · Perubahan ke-N"): never its alasan, SK or usulan.
        $item->load('ditambahkanOleh:id,nomor_urut');
        $ids = $item->pluck('id');
        $jenis = DB::table('jenis_penugasan')->whereIn('id', $item->pluck('jenis_penugasan_id')->unique())->get(['id', 'nama', 'warna', 'warna_teks'])->keyBy('id');
        $namaIrban = DB::table('irban')->whereIn('id', $item->pluck('irban_id')->unique())->pluck('nama', 'id');
        $selesai = $this->realisasi->basis()->whereIn('spt.pkpt_item_id', $ids)
            ->selectRaw('spt.pkpt_item_id, count(*) as jumlah')->groupBy('spt.pkpt_item_id')->pluck('jumlah', 'pkpt_item_id');
        // Accepted SPTs of a public klasifikasi only (PRD §9.4: KHUSUS never reaches the public site), at kecamatan level.
        $wilayah = $this->realisasi->labelWilayah($this->realisasi->objekSpt()
            ->join('spt', 'spt.id', '=', 'spt_objek.spt_id')
            ->join('klasifikasi', 'klasifikasi.id', '=', 'spt.klasifikasi_id')
            ->where('klasifikasi.tampil_publik', true)
            ->where('spt.status_pengajuan', StatusPengajuan::Diterima->value)
            ->whereIn('spt.pkpt_item_id', $ids)
            ->addSelect('spt.pkpt_item_id as kunci')->get(), false);

        $hasil = $item->map(function ($i) use ($jenis, $namaIrban, $selesai, $wilayah) {
            [$mulai, $akhir] = $this->kesesuaian->rentang($i);
            $j = $jenis[$i->jenis_penugasan_id];

            return [
                'id' => $i->id,
                'jenis' => ['id' => $j->id, 'nama' => $j->nama, 'warna' => $j->warna, 'warna_teks' => $j->warna_teks],
                'irban' => $namaIrban[$i->irban_id],
                'mulai' => $mulai,
                'selesai' => $akhir,
                'semula' => $i->semula(),
                'tambahan_ke' => $i->ditambahkanOleh?->nomor_urut,
                'wilayah' => $wilayah[$i->id] ?? self::BELUM_DITENTUKAN,
                'selesaiSpt' => (int) ($selesai[$i->id] ?? 0),
                'target' => $i->target_spt ?? 1,
            ];
        })->all();
        usort($hasil, fn ($a, $b) => [$a['jenis']['nama'], $a['mulai']->toDateString(), $a['id']] <=> [$b['jenis']['nama'], $b['mulai']->toDateString(), $b['id']]);

        return $hasil;
    }

    /**
     * Rows per jenis (in item order) with greedy lanes: an item joins the first lane whose last bar ends in a column
     * strictly before its start column. Bars: {item, mulai, selesai, semula} as grid columns 1–60.
     *
     * `mingguBulan`: weeks per month (4 or 5, MingguPkpt), so the view can hatch column 5 of a 4-week month.
     *
     * @return array{baris: list<array{jenis: array, jumlah: int, lajur: list<list<array>>}>, hariIni: ?int, mingguBulan: array<int, int>}
     */
    public function linimasa(array $item, int $tahun): array
    {
        $baris = [];
        foreach ($item as $i) {
            $baris[$i['jenis']['id']] ??= ['jenis' => $i['jenis'], 'item' => []];
            $baris[$i['jenis']['id']]['item'][] = $i;
        }

        $hasil = [];
        foreach ($baris as $b) {
            usort($b['item'], fn ($x, $y) => [$x['mulai']->toDateString(), $x['id']] <=> [$y['mulai']->toDateString(), $y['id']]);
            $lajur = [];
            foreach ($b['item'] as $i) {
                $bar = [
                    'item' => $i,
                    'mulai' => $this->kolom($i['mulai'], $tahun),
                    'selesai' => $this->kolom($i['selesai'], $tahun),
                    'semula' => $i['semula'] ? [$this->kolom($i['semula'][0], $tahun), $this->kolom($i['semula'][1], $tahun)] : null,
                ];
                $n = 0;
                while (isset($lajur[$n]) && end($lajur[$n])['selesai'] >= $bar['mulai']) {
                    $n++;
                }
                $lajur[$n][] = $bar;
            }
            $hasil[] = ['jenis' => $b['jenis'], 'jumlah' => count($b['item']), 'lajur' => $lajur];
        }

        $hariIni = CarbonImmutable::now();

        $mingguBulan = [];
        foreach (range(1, 12) as $bulan) {
            $mingguBulan[$bulan] = count(MingguPkpt::daftar($tahun, $bulan));
        }

        return ['baris' => $hasil, 'hariIni' => $hariIni->year === $tahun ? $this->kolom($hariIni, $tahun) : null, 'mingguBulan' => $mingguBulan];
    }

    /**
     * 12 months; an item is listed under every PKPT week (MingguPkpt) its effective range overlaps. `minggu` holds only
     * non-empty weeks, keyed 1–5; `jumlah` counts distinct items in the month.
     *
     * @return list<array{bulan: int, nama: string, jumlah: int, minggu: array<int, list<array>>}>
     */
    public function bulanan(array $item, int $tahun): array
    {
        $minggu = array_fill_keys(range(1, 12), []);
        foreach ($item as $i) {
            foreach (MingguPkpt::untukRentang($i['mulai'], $i['selesai'], $tahun) as $w) {
                $minggu[$w['bulan']][$w['minggu']][] = $i;
            }
        }

        $hasil = [];
        foreach ($minggu as $bulan => $m) {
            ksort($m);
            $hasil[] = ['bulan' => $bulan, 'nama' => CarbonImmutable::create($tahun, $bulan, 1)->translatedFormat('F'),
                'jumlah' => count(array_unique(array_column(array_merge(...array_values($m)), 'id'))), 'minggu' => $m];
        }

        return $hasil;
    }

    /** @return list<array{irban: string, daftar: array{Kecamatan: list<string>, 'Dinas/Badan': list<string>, BUMD: list<string>}}> */
    public function wilayah(int $tahun): array
    {
        return array_map(fn ($w) => ['irban' => $w['irban'], 'daftar' => [
            'Kecamatan' => $w['kecamatan'], 'Dinas/Badan' => $w['dinas_badan'], 'BUMD' => $w['bumd'],
        ]], app(WilayahPengawasan::class)->untuk($tahun));
    }

    /** Grid column 1–60: (bulan − 1) × 5 + week (MingguPkpt); weeks owned by another year clamp to its first/last column. */
    private function kolom(CarbonImmutable $t, int $tahun): int
    {
        $w = MingguPkpt::dari($t);

        return match (true) {
            $w['tahun'] < $tahun => 1,
            $w['tahun'] > $tahun => 60,
            default => ($w['bulan'] - 1) * 5 + $w['minggu'],
        };
    }
}
