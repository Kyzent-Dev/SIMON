<?php

namespace Database\Seeders;

use App\Models\Irban;
use Illuminate\Database\Seeder;

// Reference data: runs in production too (the five Irban and their number codes are fixed).
class IrbanSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['I', 'II', 'III', 'IV', 'V'] as $n) {
            Irban::firstOrCreate(['nama' => "Irban {$n}"], ['kode_nomor' => "INSPEKTORAT-{$n}"]);
        }
    }
}
