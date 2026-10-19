<?php

namespace Database\Seeders;

use App\Enums\StatusPkpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use Illuminate\Database\Seeder;

// Dev only. Run after IrbanSeeder and JenisPenugasanSeeder.
class PkptSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $tahun = (int) now()->year;
        $jenis = JenisPenugasan::orderBy('id')->pluck('id')->all();
        $irban = Irban::whereIn('nama', ['Irban I', 'Irban II'])->pluck('id')->all();
        if (! $jenis || ! $irban) {
            return;
        }

        $sekarang = Pkpt::firstOrCreate(['tahun' => $tahun], [
            'nomor_sk' => "100.3.3.2/123/KPTS/INSPEKTORAT/{$tahun}", 'tanggal_sk' => "{$tahun}-01-02",
            'status' => StatusPkpt::Disahkan, 'disahkan_pada' => "{$tahun}-01-02 08:00:00",
        ]);
        if ($sekarang->wasRecentlyCreated) {
            foreach ($irban as $i => $irbanId) {
                foreach ([['02-02', '02-20'], ['06-01', '06-19'], ['09-07', '09-25']] as $n => [$mulai, $selesai]) {
                    $sekarang->items()->create([
                        'irban_id' => $irbanId, 'jenis_penugasan_id' => $jenis[($i + $n) % count($jenis)],
                        'tanggal_mulai' => "{$tahun}-{$mulai}", 'tanggal_selesai' => "{$tahun}-{$selesai}", 'target_spt' => 2,
                    ]);
                }
            }
        }

        $depan = $tahun + 1;
        $draf = Pkpt::firstOrCreate(['tahun' => $depan], ['status' => StatusPkpt::Draf]);
        if ($draf->wasRecentlyCreated) {
            foreach ([['01-12', '01-30'], ['04-06', '04-24']] as $n => [$mulai, $selesai]) {
                $draf->items()->create([
                    'irban_id' => $irban[0], 'jenis_penugasan_id' => $jenis[$n % count($jenis)],
                    'tanggal_mulai' => "{$depan}-{$mulai}", 'tanggal_selesai' => "{$depan}-{$selesai}",
                ]);
            }
        }
    }
}
