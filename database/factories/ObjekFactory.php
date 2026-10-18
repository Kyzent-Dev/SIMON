<?php

namespace Database\Factories;

use App\Models\Objek;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Objek> */
class ObjekFactory extends Factory
{
    protected $model = Objek::class;

    public function definition(): array
    {
        return ['nama' => 'Objek '.fake()->unique()->numberBetween(1, 100000), 'jenis' => 'kecamatan', 'parent_id' => null];
    }
}
