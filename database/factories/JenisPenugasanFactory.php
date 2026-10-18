<?php

namespace Database\Factories;

use App\Models\JenisPenugasan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JenisPenugasan> */
class JenisPenugasanFactory extends Factory
{
    protected $model = JenisPenugasan::class;

    public function definition(): array
    {
        return ['nama' => 'Jenis '.fake()->unique()->bothify('??##'), 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF'];
    }
}
