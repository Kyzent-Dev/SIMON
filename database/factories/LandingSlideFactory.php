<?php

namespace Database\Factories;

use App\Models\LandingSlide;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<LandingSlide> Files are not written; use GambarSlide when a test needs them. */
class LandingSlideFactory extends Factory
{
    protected $model = LandingSlide::class;

    public function definition(): array
    {
        return [
            'judul' => fake()->sentence(4),
            'gambar' => 'slide/'.Str::uuid(),
            'alt' => 'Kegiatan pengawasan',
            'aktif' => true,
            'urutan' => fn () => (int) LandingSlide::max('urutan') + 1,
        ];
    }
}
