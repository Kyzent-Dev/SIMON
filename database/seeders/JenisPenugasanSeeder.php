<?php

namespace Database\Seeders;

use App\Models\JenisPenugasan;
use App\Services\ColorRules;
use Illuminate\Database\Seeder;

// Dev only. PRD §7.4 sample types with swatches 1-3.
class JenisPenugasanSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        foreach (['Evaluasi RB dan Reviu DAK', 'Pengawasan Dana BOS', 'Reviu LKjIP'] as $i => $nama) {
            $s = ColorRules::SWATCHES[$i];
            JenisPenugasan::firstOrCreate(['nama' => $nama], ['warna' => $s['hex'], 'warna_teks' => $s['label']]);
        }
    }
}
