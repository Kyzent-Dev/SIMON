<?php

namespace Database\Factories;

use App\Models\PkptPerubahan;
use App\Models\PkptPerubahanRiwayat;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PkptPerubahanRiwayat> */
class PkptPerubahanRiwayatFactory extends Factory
{
    protected $model = PkptPerubahanRiwayat::class;

    public function definition(): array
    {
        return ['perubahan_id' => PkptPerubahan::factory(), 'kejadian' => 'dibuat', 'oleh' => null, 'catatan' => null];
    }
}
