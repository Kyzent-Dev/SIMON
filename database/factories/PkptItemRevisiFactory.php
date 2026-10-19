<?php

namespace Database\Factories;

use App\Enums\StatusRevisi;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PkptItemRevisi> */
class PkptItemRevisiFactory extends Factory
{
    protected $model = PkptItemRevisi::class;

    public function definition(): array
    {
        return [
            'pkpt_item_id' => PkptItem::factory(),
            'tanggal_mulai_semula' => '2026-03-02',
            'tanggal_selesai_semula' => '2026-03-20',
            'tanggal_mulai' => '2026-04-01',
            'tanggal_selesai' => '2026-04-20',
            'alasan' => 'Menyesuaikan jadwal.',
            'status' => StatusRevisi::Menunggu,
            'diajukan_oleh' => User::factory()->irban(),
        ];
    }

    public function disetujui(): static
    {
        return $this->decided(StatusRevisi::Disetujui);
    }

    public function ditolak(): static
    {
        return $this->decided(StatusRevisi::Ditolak)->state(['komentar' => 'Tidak sesuai.']);
    }

    private function decided(StatusRevisi $status): static
    {
        return $this->state(['status' => $status, 'diputuskan_oleh' => User::factory()->pimpinan(), 'diputuskan_pada' => now()]);
    }
}
