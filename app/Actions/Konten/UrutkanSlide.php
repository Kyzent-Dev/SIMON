<?php

namespace App\Actions\Konten;

use App\Models\LandingSlide;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Set the full order of the slides: the list must hold every slide id exactly once; urutan becomes 1..n. */
class UrutkanSlide
{
    public function handle(array $ids): void
    {
        $ids = array_map('intval', array_values($ids));
        DB::transaction(function () use ($ids) {
            $ada = LandingSlide::lockForUpdate()->pluck('id')->sort()->values()->all();
            $kirim = $ids;
            sort($kirim);
            if ($kirim !== $ada) {
                throw ValidationException::withMessages(['urutan' => 'Urutan slide tidak valid. Muat ulang halaman lalu coba lagi.']);
            }
            $slides = LandingSlide::whereIn('id', $ids)->get()->keyBy('id');
            foreach ($ids as $i => $id) {
                $slides[$id]->update(['urutan' => $i + 1]);
            }
        });
    }
}
