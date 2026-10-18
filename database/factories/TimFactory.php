<?php

namespace Database\Factories;

use App\Models\Irban;
use App\Models\Tim;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tim> */
class TimFactory extends Factory
{
    protected $model = Tim::class;

    public function definition(): array
    {
        return ['irban_id' => Irban::factory(), 'tahun' => 2026, 'nama' => 'Tim '.fake()->unique()->numberBetween(1, 100000)];
    }
}
