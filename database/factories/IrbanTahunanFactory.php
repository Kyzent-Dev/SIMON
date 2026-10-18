<?php

namespace Database\Factories;

use App\Models\Irban;
use App\Models\IrbanTahunan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IrbanTahunan> */
class IrbanTahunanFactory extends Factory
{
    protected $model = IrbanTahunan::class;

    public function definition(): array
    {
        return ['irban_id' => Irban::factory(), 'tahun' => 2026, 'ppj_pegawai_id' => null];
    }
}
