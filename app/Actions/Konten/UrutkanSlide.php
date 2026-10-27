<?php

namespace App\Actions\Konten;

use App\Models\LandingSlide;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Set the full order of the slides: the list must hold every slide id exactly once; urutan becomes 1..n. */
class UrutkanSlide
{
    /** Swap slide $id with its neighbour ($arah −1 up, +1 down); at the end nothing moves. */
    public function geser(int $id, int $arah): void
    {
        $ids = $this->urutan();
        $i = array_search($id, $ids, true);
        abort_if($i === false, 404);
        $j = $i + $arah;
        if (! isset($ids[$j])) {
            return;
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->handle($ids);
    }

    /** Move slide $id to 0-based position $posisi (drag reorder). */
    public function pindahkan(int $id, int $posisi): void
    {
        $ids = $this->urutan();
        $dari = array_search($id, $ids, true);
        abort_if($dari === false, 404);
        array_splice($ids, $dari, 1);
        array_splice($ids, max(0, min($posisi, count($ids))), 0, [$id]);
        $this->handle($ids);
    }

    private function urutan(): array
    {
        return LandingSlide::orderBy('urutan')->orderBy('id')->pluck('id')->all();
    }

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
