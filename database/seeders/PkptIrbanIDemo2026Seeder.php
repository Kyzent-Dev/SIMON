<?php

namespace Database\Seeders;

use App\Enums\StatusPkpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Services\ColorRules;
use Illuminate\Database\Seeder;

// Dev only. Run after PkptSeeder and before SptSeeder. Fixed year 2026 demo plan for Irban I.
class PkptIrbanIDemo2026Seeder extends Seeder
{
    private const TAHUN = 2026;

    /** [mulai, selesai, jenis (master name), keterangan] */
    private const ROWS = [
        ['2026-01-12', '2026-01-23', 'Evaluasi Risk Register', null],
        ['2026-01-26', '2026-01-30', 'Evaluasi RB dan Reviu DAK', null],
        ['2026-02-02', '2026-02-27', 'Pengawasan Dana BOS', '1 tim 1 minggu 2 sekolah'],
        ['2026-03-02', '2026-03-06', 'Reviu LKjIP', null],
        ['2026-03-09', '2026-03-13', 'Reviu DBH', null],
        ['2026-03-23', '2026-03-27', 'Reviu Tata Kelola Pajak', null],
        ['2026-03-30', '2026-04-03', 'Evaluasi RB dan Reviu DAK', null],
        ['2026-04-06', '2026-04-10', 'Evaluasi SAKIP PD', null],
        ['2026-04-13', '2026-05-08', 'Pengawasan Tata Kelola Desa/Keuangan Desa', '1 tim 1 minggu 2 desa'],
        ['2026-05-11', '2026-05-22', 'Pendampingan dan Evaluasi ZI PD', null],
        ['2026-05-25', '2026-06-05', 'Audit Kinerja Program Strategis Daerah', null],
        ['2026-06-08', '2026-06-26', 'Audit Ketaatan Puskesmas', '1 tim 1 minggu 1 puskesmas'],
        ['2026-06-29', '2026-07-03', 'Evaluasi RB dan Reviu DAK', null],
        ['2026-07-06', '2026-07-17', 'Reviu Kinerja Pelayanan Publik Sektor Kependudukan dan Pencatatan Sipil', null],
        ['2026-07-20', '2026-07-24', 'Monev Program Strategis Nasional Ketahanan Pangan dan Pembangunan Tiga Juta Rumah', null],
        ['2026-07-27', '2026-08-14', 'Audit Ketaatan Kecamatan', '1 tim 1 minggu 1 kecamatan'],
        ['2026-08-17', '2026-08-28', 'Audit Kinerja Program Prioritas Daerah', null],
        ['2026-08-31', '2026-09-25', 'Audit Ketaatan OPD', '1 tim 1 minggu 1 OPD'],
        ['2026-09-28', '2026-10-02', 'Evaluasi Pelayanan Publik Kemenpan RB', null],
        ['2026-10-05', '2026-10-09', 'Evaluasi Pelayanan Publik Ombudsman', null],
        ['2026-10-12', '2026-10-16', 'Audit Tujuan Tertentu Perusda/BUMD', 'Perumdam Lematang Enim'],
        ['2026-10-19', '2026-11-13', 'Pengawasan Tata Kelola Desa/Keuangan Desa', '1 tim 1 minggu 2 desa'],
        ['2026-11-16', '2026-11-27', 'Evaluasi Risk Register', null],
        ['2026-11-30', '2026-12-04', 'Evaluasi RB dan Reviu DAK', null],
        ['2026-12-07', '2026-12-25', 'Monev', null],
    ];

    public function run(): void
    {
        // Deletes items: only local/testing, never a staging copy of real data.
        if (! app()->environment('local', 'testing')) {
            return;
        }

        $irbanId = Irban::where('nama', 'Irban I')->value('id');
        if (! $irbanId) {
            return;
        }

        // An existing PKPT 2026 keeps its status, even when it is not Disahkan.
        $pkpt = Pkpt::firstOrCreate(['tahun' => self::TAHUN], [
            'nomor_sk' => '100.3.3.2/123/KPTS/INSPEKTORAT/'.self::TAHUN, 'tanggal_sk' => self::TAHUN.'-01-02',
            'status' => StatusPkpt::Disahkan, 'disahkan_pada' => self::TAHUN.'-01-02 08:00:00',
        ]);

        $urutanJenis = array_values(array_unique(array_column(self::ROWS, 2)));
        $keep = [];
        foreach (self::ROWS as [$mulai, $selesai, $nama, $keterangan]) {
            $jenis = JenisPenugasan::where('nama', $nama)->first() ?? $this->buatJenis($nama, $urutanJenis);
            $keep[] = PkptItem::firstOrCreate([
                'pkpt_id' => $pkpt->id, 'irban_id' => $irbanId, 'jenis_penugasan_id' => $jenis->id,
                'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
            ], ['keterangan' => $keterangan, 'target_spt' => null,
                // Pengesahan snapshot ("jadwal semula"), as SahkanPkpt would write it.
                'tanggal_mulai_disahkan' => $pkpt->isDraf() ? null : $mulai, 'tanggal_selesai_disahkan' => $pkpt->isDraf() ? null : $selesai])->id;
        }

        PkptItem::where('pkpt_id', $pkpt->id)->where('irban_id', $irbanId)
            ->whereNotIn('id', $keep)
            ->whereNotIn('id', Spt::whereNotNull('pkpt_item_id')->select('pkpt_item_id'))
            ->delete();
    }

    /** @param list<string> $urutanJenis jenis-jenis unik dalam urutan ROWS */
    private function buatJenis(string $nama, array $urutanJenis): JenisPenugasan
    {
        // Stable per name (position in ROWS), not the table size, so a rerun or extra rows do not shift colors.
        $posisi = array_search($nama, $urutanJenis, true);
        if ($posisi === false) {
            throw new \LogicException("Jenis penugasan {$nama} tidak ada di ROWS.");
        }
        $s = ColorRules::SWATCHES[$posisi % count(ColorRules::SWATCHES)];

        return JenisPenugasan::create(['nama' => $nama, 'warna' => $s['hex'], 'warna_teks' => $s['label']]);
    }
}
