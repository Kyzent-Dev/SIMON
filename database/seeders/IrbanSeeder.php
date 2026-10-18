<?php

namespace Database\Seeders;

use App\Models\Irban;
use Illuminate\Database\Seeder;

// Dev only, like UserSeeder.
class IrbanSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        foreach (['I', 'II', 'III', 'IV', 'V'] as $n) {
            Irban::firstOrCreate(['nama' => "Irban {$n}"], ['kode_nomor' => "INSPEKTORAT-{$n}"]);
        }
    }
}
