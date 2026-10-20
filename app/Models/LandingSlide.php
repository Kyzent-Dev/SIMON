<?php

namespace App\Models;

use Database\Factories\LandingSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable(['judul', 'kutipan', 'pemilik_nama', 'pemilik_jabatan', 'gambar', 'alt', 'dekoratif', 'aktif', 'urutan'])]
class LandingSlide extends Model
{
    /** @use HasFactory<LandingSlideFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['dekoratif' => 'boolean', 'aktif' => 'boolean', 'urutan' => 'integer'];
    }

    /** Public URL of one WebP variant (1920 or 960). */
    public function gambarUrl(int $lebar): string
    {
        return Storage::disk('public')->url("{$this->gambar}-{$lebar}.webp");
    }
}
