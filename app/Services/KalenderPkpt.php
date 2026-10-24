<?php

namespace App\Services;

use App\Enums\StatusSpt;
use App\Models\Spt;
use App\Services\Publik\RealisasiPublik;
use App\Support\MingguPkpt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Kalender PKPT satu irban: item PKPT Disahkan ditempatkan per minggu (MingguPkpt) berdasarkan jadwal efektif. */
class KalenderPkpt
{
    public function __construct(private RealisasiPublik $realisasi, private KesesuaianPkpt $kesesuaian) {}

    /**
     * @return list<array{bulan: int, nama: string, minggu: array<int, array{ada: bool, mulai: ?CarbonImmutable, selesai: ?CarbonImmutable, ini: bool, item: list<array>}>}>
     *                                                                                                                                                                       item = {id, jenis: {id, nama, warna, warna_teks}, keterangan, mulai, selesai, semula: ?array{0,1}, direvisi, tambahan_ke: ?int, selesai_spt, target}
     */
    public function untuk(int $tahun, int $irbanId): array
    {
        $item = $this->realisasi->itemDisahkan($tahun, $irbanId)->load('ditambahkanOleh:id,nomor_urut');
        $ids = $item->pluck('id');
        $jenis = DB::table('jenis_penugasan')->whereIn('id', $item->pluck('jenis_penugasan_id')->unique())->get(['id', 'nama', 'warna', 'warna_teks'])->keyBy('id');
        $selesai = Spt::query()->berstatus(StatusSpt::Selesai)->whereIn('pkpt_item_id', $ids)
            ->selectRaw('pkpt_item_id, count(*) as jumlah')->groupBy('pkpt_item_id')->pluck('jumlah', 'pkpt_item_id');

        $sel = [];
        foreach ($item as $i) {
            [$mulai, $akhir] = $this->kesesuaian->rentang($i);
            $baris = [
                'id' => $i->id,
                'jenis' => (array) $jenis[$i->jenis_penugasan_id],
                'keterangan' => $i->keterangan,
                'mulai' => $mulai,
                'selesai' => $akhir,
                'semula' => $i->semula(),
                'direvisi' => $i->direvisi(),
                'tambahan_ke' => $i->ditambahkanOleh?->nomor_urut,
                'selesai_spt' => (int) ($selesai[$i->id] ?? 0),
                'target' => $i->target_spt ?? 1,
            ];
            foreach (MingguPkpt::untukRentang($mulai, $akhir, $tahun) as $w) {
                $sel[$w['bulan']][$w['minggu']][] = $baris;
            }
        }

        $hariIni = MingguPkpt::dari(CarbonImmutable::now('Asia/Jakarta'));
        $hasil = [];
        foreach (range(1, 12) as $bulan) {
            $daftar = array_column(MingguPkpt::daftar($tahun, $bulan), null, 'minggu');
            $minggu = [];
            foreach (range(1, 5) as $n) {
                $isi = $sel[$bulan][$n] ?? [];
                usort($isi, fn ($a, $b) => [$a['mulai']->toDateString(), $a['id']] <=> [$b['mulai']->toDateString(), $b['id']]);
                $minggu[$n] = [
                    'ada' => isset($daftar[$n]),
                    'mulai' => $daftar[$n]['mulai'] ?? null,
                    'selesai' => $daftar[$n]['selesai'] ?? null,
                    'ini' => $hariIni === ['tahun' => $tahun, 'bulan' => $bulan, 'minggu' => $n],
                    'item' => $isi,
                ];
            }
            $hasil[] = ['bulan' => $bulan, 'nama' => CarbonImmutable::create($tahun, $bulan, 1)->translatedFormat('F'), 'minggu' => $minggu];
        }

        return $hasil;
    }
}
