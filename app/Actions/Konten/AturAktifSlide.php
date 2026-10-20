<?php

namespace App\Actions\Konten;

use App\Models\LandingSlide;

class AturAktifSlide
{
    public function handle(LandingSlide $slide, bool $aktif): void
    {
        $slide->update(['aktif' => $aktif]);
    }
}
