<?php

namespace Database\Factories;

use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PkptItem> */
class PkptItemFactory extends Factory
{
    protected $model = PkptItem::class;

    public function definition(): array
    {
        return [
            'pkpt_id' => Pkpt::factory(),
            'irban_id' => Irban::factory(),
            'jenis_penugasan_id' => JenisPenugasan::factory(),
            'tanggal_mulai' => '2026-03-02',
            'tanggal_selesai' => '2026-03-20',
            'keterangan' => null,
            'target_spt' => null,
        ];
    }
}
