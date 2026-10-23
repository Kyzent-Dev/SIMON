<?php

namespace Database\Factories;

use App\Enums\JenisPerubahan;
use App\Enums\StatusPerubahan;
use App\Models\Pkpt;
use App\Models\PkptPerubahan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PkptPerubahan> */
class PkptPerubahanFactory extends Factory
{
    protected $model = PkptPerubahan::class;

    public function definition(): array
    {
        return [
            'pkpt_id' => Pkpt::factory()->disahkan(),
            'jenis' => JenisPerubahan::Perubahan,
            'alasan' => 'Menyesuaikan jadwal pemeriksaan.',
            'status' => StatusPerubahan::Draf,
            'sumber' => 'admin',
            'dibuat_oleh' => User::factory()->admin(),
        ];
    }

    public function diajukan(): static
    {
        return $this->state(['status' => StatusPerubahan::Diajukan, 'diajukan_pada' => now()]);
    }

    /** nomor_urut must be unique per PKPT: pass it explicitly when making several approved packages of one PKPT. */
    public function disetujui(int $nomor = 1): static
    {
        return $this->state([
            'status' => StatusPerubahan::Disetujui,
            'nomor_urut' => $nomor,
            'diajukan_pada' => now(),
            'diputuskan_oleh' => User::factory()->pimpinan(),
            'diputuskan_pada' => now(),
        ]);
    }

    public function ditolak(): static
    {
        return $this->state([
            'status' => StatusPerubahan::Ditolak,
            'diajukan_pada' => now(),
            'diputuskan_oleh' => User::factory()->pimpinan(),
            'diputuskan_pada' => now(),
            'komentar_putusan' => 'Belum sesuai.',
        ]);
    }
}
