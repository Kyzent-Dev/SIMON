<?php

namespace Database\Seeders;

use App\Models\Irban;
use App\Models\IrbanWilayah;
use App\Models\Objek;
use Illuminate\Database\Seeder;

// Dev only. Irban I wilayah for the current year: the sample kecamatan, dinas/badan and BUMD from ObjekSeeder.
class IrbanWilayahSeeder extends Seeder
{
    public function run(): void
    {
        $irban = Irban::where('nama', 'Irban I')->first();
        if (app()->isProduction() || ! $irban) {
            return;
        }

        foreach (Objek::whereIn('jenis', ['kecamatan', 'dinas_badan', 'bumd'])->pluck('id') as $id) {
            IrbanWilayah::firstOrCreate(['tahun' => (int) now('Asia/Jakarta')->year, 'objek_id' => $id], ['irban_id' => $irban->id]);
        }
    }
}
