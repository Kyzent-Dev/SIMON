<?php

namespace App\Services\Publik;

use App\Enums\StatusPengajuan;
use App\Services\KesesuaianPkpt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Public Jadwal PKPT (PRD §7.2): items of the year's Disahkan PKPT as plain arrays, plus timeline/monthly layouts. */
class JadwalPublik
{
    public const BELUM_DITENTUKAN = 'Belum ditentukan';

    /** irban_wilayah objek jenis → panel list. */
    private const DAFTAR = ['kecamatan' => 'Kecamatan', 'dinas_badan' => 'Dinas/Badan', 'rsud' => 'Dinas/Badan', 'bumd' => 'BUMD'];

    public function __construct(private RealisasiPublik $realisasi, private KesesuaianPkpt $kesesuaian) {}

    /**
     * @return list<array{id: int, jenis: array, irban: string, mulai: CarbonImmutable, selesai: CarbonImmutable,
     *   semula: ?array{0: CarbonImmutable, 1: CarbonImmutable}, wilayah: string, selesaiSpt: int, target: int}>
     *   ordered by jenis name, effective start, id
     */
    public function item(int $tahun, ?int $irban): array
    {
        $item = $this->realisasi->itemDisahkan($tahun, $irban);
        if ($item->isEmpty()) {
            return [];
        }
        $ids = $item->pluck('id');
        $jenis = DB::table('jenis_penugasan')->whereIn('id', $item->pluck('jenis_penugasan_id')->unique())->get(['id', 'nama', 'warna', 'warna_teks'])->keyBy('id');
        $namaIrban = DB::table('irban')->whereIn('id', $item->pluck('irban_id')->unique())->pluck('nama', 'id');
        $selesai = $this->realisasi->basis()->whereIn('spt.pkpt_item_id', $ids)
            ->selectRaw('spt.pkpt_item_id, count(*) as jumlah')->groupBy('spt.pkpt_item_id')->pluck('jumlah', 'pkpt_item_id');
        // Any accepted SPT of the item (not only public ones), always at kecamatan level.
        $wilayah = $this->realisasi->labelWilayah($this->realisasi->objekSpt()
            ->join('spt', 'spt.id', '=', 'spt_objek.spt_id')
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
                'semula' => $i->revisiDisetujuiTerakhir() ? [$i->tanggal_mulai->startOfDay(), $i->tanggal_selesai->startOfDay()] : null,
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
     * @return array{baris: list<array{jenis: array, jumlah: int, lajur: list<list<array>>}>, hariIni: ?int}
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

        return ['baris' => $hasil, 'hariIni' => $hariIni->year === $tahun ? $this->kolom($hariIni, $tahun) : null];
    }

    /**
     * 12 months; an item is listed in every month its effective range overlaps: under the week of its start date in its
     * start month, under week 1 in later months. `minggu` holds only non-empty weeks, keyed 1–5.
     *
     * @return list<array{bulan: int, nama: string, jumlah: int, minggu: array<int, list<array>>}>
     */
    public function bulanan(array $item, int $tahun): array
    {
        $hasil = [];
        foreach (range(1, 12) as $bulan) {
            $awal = CarbonImmutable::create($tahun, $bulan, 1);
            $akhir = $awal->endOfMonth()->startOfDay();
            $minggu = [];
            foreach ($item as $i) {
                if ($i['mulai']->gt($akhir) || $i['selesai']->lt($awal)) {
                    continue;
                }
                $minggu[$i['mulai']->gte($awal) ? $this->minggu($i['mulai']) : 1][] = $i;
            }
            ksort($minggu);
            $hasil[] = ['bulan' => $bulan, 'nama' => $awal->translatedFormat('F'), 'jumlah' => array_sum(array_map('count', $minggu)), 'minggu' => $minggu];
        }

        return $hasil;
    }

    /** @return list<array{irban: string, daftar: array{Kecamatan: list<string>, 'Dinas/Badan': list<string>, BUMD: list<string>}}> */
    public function wilayah(int $tahun): array
    {
        $objek = DB::table('irban_wilayah')->join('objek', 'objek.id', '=', 'irban_wilayah.objek_id')
            ->where('irban_wilayah.tahun', $tahun)->whereIn('objek.jenis', array_keys(self::DAFTAR))
            ->orderBy('objek.nama')->get(['irban_wilayah.irban_id', 'objek.nama', 'objek.jenis'])->groupBy('irban_id');

        return DB::table('irban')->orderBy('nama')->get(['id', 'nama'])->map(function ($i) use ($objek) {
            $daftar = ['Kecamatan' => [], 'Dinas/Badan' => [], 'BUMD' => []];
            foreach ($objek[$i->id] ?? [] as $o) {
                $daftar[self::DAFTAR[$o->jenis]][] = $o->nama;
            }

            return ['irban' => $i->nama, 'daftar' => $daftar];
        })->all();
    }

    /** Grid column 1–60: (bulan − 1) × 5 + week of the month (1–5); dates outside $tahun clamp to its first/last column. */
    private function kolom(CarbonImmutable $t, int $tahun): int
    {
        return match (true) {
            $t->year < $tahun => 1,
            $t->year > $tahun => 60,
            default => ($t->month - 1) * 5 + $this->minggu($t),
        };
    }

    private function minggu(CarbonImmutable $t): int
    {
        return min(5, (int) ceil($t->day / 7));
    }
}
