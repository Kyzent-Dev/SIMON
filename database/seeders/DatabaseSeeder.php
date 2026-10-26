<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Reference rows only in production (E11); klasifikasi comes from its migration.
        $this->call([IrbanSeeder::class, ObjekSeeder::class, JenisPenugasanSeeder::class]);
        if (app()->isProduction()) {
            return;
        }

        $this->call([
            UserSeeder::class, TimSeeder::class, IrbanWilayahSeeder::class, PkptSeeder::class, PkptIrbanIDemo2026Seeder::class,
            SptSeeder::class, RealisasiDemoSeeder::class, KomentarSeeder::class, NotifikasiSeeder::class, LandingSeeder::class,
            DemoPresentasiSeeder::class,
        ]);
    }
}
