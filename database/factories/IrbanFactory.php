<?php

namespace Database\Factories;

use App\Models\Irban;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Irban> */
class IrbanFactory extends Factory
{
    protected $model = Irban::class;

    public function definition(): array
    {
        return [
            'nama' => 'Irban '.fake()->unique()->bothify('??##'),
            'kode_nomor' => 'KODE-'.fake()->unique()->bothify('??##'),
            'email_notifikasi' => null,
            'email_tembusan' => null,
        ];
    }
}
