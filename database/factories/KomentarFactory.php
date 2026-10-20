<?php

namespace Database\Factories;

use App\Enums\StatusPermintaan;
use App\Models\Komentar;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Komentar> Default: a plain root comment by a pimpinan on an accepted SPT. */
class KomentarFactory extends Factory
{
    protected $model = Komentar::class;

    public function definition(): array
    {
        return [
            'spt_id' => Spt::factory()->diterima(),
            'user_id' => User::factory()->pimpinan(),
            'isi' => fake()->sentence(),
        ];
    }

    public function mintaPenjelasan(): static
    {
        return $this->state(['minta_penjelasan' => true, 'status_permintaan' => StatusPermintaan::Terbuka]);
    }

    public function balasanDari(Komentar $root, ?User $user = null): static
    {
        return $this->state(fn () => ['spt_id' => $root->spt_id, 'parent_id' => $root->id, 'user_id' => $user ?? User::factory()->irban()]);
    }
}
