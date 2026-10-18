<?php

namespace Database\Factories;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pegawai> */
class PegawaiFactory extends Factory
{
    protected $model = Pegawai::class;

    public function definition(): array
    {
        return [
            'nama_bergelar' => fake()->name(),
            'nip' => fake()->unique()->numerify('##################'),
            'pangkat' => fake()->randomElement(['Penata', 'Pembina', 'Penata Muda']),
            'golongan' => fake()->randomElement(['III/a', 'III/c', 'IV/a']),
            'jabatan' => fake()->jobTitle(),
            'aktif' => true,
        ];
    }
}
