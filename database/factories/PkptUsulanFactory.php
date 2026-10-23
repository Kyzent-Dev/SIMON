<?php

namespace Database\Factories;

use App\Enums\AksiUsulan;
use App\Enums\StatusUsulan;
use App\Models\Pegawai;
use App\Models\PkptItem;
use App\Models\PkptUsulan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PkptUsulan> */
class PkptUsulanFactory extends Factory
{
    protected $model = PkptUsulan::class;

    public function definition(): array
    {
        return [
            'pkpt_item_id' => PkptItem::factory(),
            'pkpt_id' => fn (array $a) => PkptItem::find($a['pkpt_item_id'])->pkpt_id,
            'irban_id' => fn (array $a) => PkptItem::find($a['pkpt_item_id'])->irban_id,
            'aksi_usulan' => AksiUsulan::Geser,
            'tanggal_mulai' => '2026-04-01',
            'tanggal_selesai' => '2026-04-20',
            'jenis_penugasan_id' => null,
            'alasan' => 'Menyesuaikan jadwal.',
            'diajukan_oleh' => User::factory()->irban(),
            'diinput_oleh' => Pegawai::factory(),
            'status' => StatusUsulan::Terbuka,
        ];
    }

    public function ditutup(string $catatan = 'Tidak dapat ditampung.'): static
    {
        return $this->state(['status' => StatusUsulan::Ditutup, 'ditutup_oleh' => User::factory()->admin(), 'catatan_penutupan' => $catatan]);
    }
}
