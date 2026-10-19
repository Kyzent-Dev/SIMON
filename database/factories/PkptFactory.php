<?php

namespace Database\Factories;

use App\Enums\StatusPkpt;
use App\Models\Pkpt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pkpt> */
class PkptFactory extends Factory
{
    protected $model = Pkpt::class;

    public function definition(): array
    {
        // Far outside the years tests hard-code (2024–2027), so a random default never collides with an explicit one.
        return ['tahun' => fake()->unique()->numberBetween(2100, 2999), 'nomor_sk' => null, 'tanggal_sk' => null, 'status' => StatusPkpt::Draf];
    }

    public function disahkan(): static
    {
        // Attribute closures run after create()/make() overrides, so the SK follows the final tahun.
        return $this->state([
            'status' => StatusPkpt::Disahkan,
            'nomor_sk' => fn (array $a) => '100.3.3.2/1/KPTS/INSPEKTORAT/'.$a['tahun'],
            'tanggal_sk' => fn (array $a) => $a['tahun'].'-01-02',
            'disahkan_pada' => now(),
            'disahkan_oleh' => User::factory()->admin(),
        ]);
    }
}
