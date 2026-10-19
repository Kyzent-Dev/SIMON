<?php

namespace Database\Factories;

use App\Models\Spt;
use App\Models\SptKendala;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SptKendala> */
class SptKendalaFactory extends Factory
{
    protected $model = SptKendala::class;

    public function definition(): array
    {
        return [
            'spt_id' => Spt::factory()->diterima(),
            'kategori' => 'Lainnya',
            'uraian' => 'Jadwal bergeser karena objek belum siap diperiksa.',
            'dibuat_oleh' => User::factory()->irban(),
        ];
    }
}
