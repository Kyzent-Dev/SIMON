<?php

namespace Database\Seeders;

use App\Models\Objek;
use Illuminate\Database\Seeder;

// Dev only, like UserSeeder. SAMPLE names: the real Irban I wilayah table must replace them (see DECISIONS.md).
class ObjekSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $make = fn (string $nama, string $jenis, ?int $parent = null) => Objek::firstOrCreate(['nama' => $nama, 'jenis' => $jenis, 'parent_id' => $parent]);

        $kecamatan = collect(['Muara Enim', 'Ujan Mas', 'Gunung Megang', 'Benakat', 'Lawang Kidul'])->map(fn ($n) => $make($n, 'kecamatan'));
        foreach (['Dinas Pendidikan', 'Dinas Kesehatan', 'Dinas PUPR', 'Dinas Sosial', 'BAPPEDA', 'BPKAD', 'Dinas Perhubungan', 'Dinas Pertanian', 'BKPSDM'] as $n) {
            $make($n, 'dinas_badan');
        }
        $make('Perumdam Lematang Enim', 'bumd');

        $first = $kecamatan->first()->id;
        foreach ([['Desa Muara Enim Lama', 'desa'], ['Desa Pasar Muara Enim', 'desa'], ['SD Negeri 1 Muara Enim', 'sekolah'], ['Puskesmas Muara Enim', 'puskesmas']] as [$n, $j]) {
            $make($n, $j, $first);
        }
    }
}
