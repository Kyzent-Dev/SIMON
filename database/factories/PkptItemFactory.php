<?php

namespace Database\Factories;

use App\Enums\StatusItemPkpt;
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

    /** Removed by a Perubahan PKPT (soft): excluded from every view and count. */
    public function dihapus(): static
    {
        return $this->state(['status' => StatusItemPkpt::Dihapus]);
    }

    /** Snapshot taken at pengesahan = the item's own dates (move the dates afterwards to make it "direvisi"). */
    public function disahkanDari(): static
    {
        return $this->state(fn (array $a) => ['tanggal_mulai_disahkan' => $a['tanggal_mulai'], 'tanggal_selesai_disahkan' => $a['tanggal_selesai']]);
    }
}
