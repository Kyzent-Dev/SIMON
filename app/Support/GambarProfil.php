<?php

namespace App\Support;

use App\Support\Concerns\OlahGambar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Profile photo (square, 256 x 256) and background (4:1, 1600 x 400): center crop, WebP on the public disk under `profil/`. */
class GambarProfil
{
    use OlahGambar;

    /** jenis => [output width, output height, minimum width, minimum height] */
    public const UKURAN = ['foto' => [256, 256, 256, 256], 'latar' => [1600, 400, 800, 200]];

    public const TIDAK_TERBACA = 'Gambar tidak dapat dibaca. Unggah ulang file lain.';

    /** Rules for the `foto`/`latar` upload. Header readable, at most 6000 x 6000 and at least the minimum, both measured after EXIF rotation, before GD decodes it. */
    public static function rules(string $jenis, int $maksMb): array
    {
        [, , $minW, $minH] = self::UKURAN[$jenis];

        return ['required', 'bail', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.($maksMb * 1024),
            function (string $attribute, mixed $value, \Closure $fail) use ($minW, $minH) {
                $info = $value instanceof UploadedFile ? @getimagesize($value->getRealPath()) : false;
                if (! $info) {
                    $fail(self::TIDAK_TERBACA);

                    return;
                }
                [$w, $h] = in_array(self::sudutExif($value), [90, 270], true) ? [$info[1], $info[0]] : [$info[0], $info[1]];
                if ($w > 6000 || $h > 6000) {
                    $fail('Ukuran gambar maksimal 6000 × 6000 piksel.');
                } elseif ($w < $minW || $h < $minH) {
                    $fail("Ukuran gambar minimal {$minW} × {$minH} piksel.");
                }
            }];
    }

    public static function foto(UploadedFile $file): string
    {
        return self::simpan($file, 'foto');
    }

    public static function latar(UploadedFile $file): string
    {
        return self::simpan($file, 'latar');
    }

    /** Write the WebP and return its path `profil/{uuid}.webp`. Throws RuntimeException when GD cannot decode it (nothing is written then). */
    public static function simpan(UploadedFile $file, string $jenis): string
    {
        [$tw, $th] = self::UKURAN[$jenis];
        $src = self::dekode($file);
        try {
            $w = imagesx($src);
            $h = imagesy($src);
            // Center crop to the target ratio, resampled straight from the source region.
            [$cw, $ch] = $w * $th > $h * $tw ? [(int) round($h * $tw / $th), $h] : [$w, (int) round($w * $th / $tw)];
            $out = imagecreatetruecolor($tw, $th);
            imagecopyresampled($out, $src, 0, 0, intdiv($w - $cw, 2), intdiv($h - $ch, 2), $tw, $th, $cw, $ch);
            $bytes = self::webp($out, 80);
            imagedestroy($out);
        } finally {
            imagedestroy($src);
        }
        $path = 'profil/'.Str::uuid().'.webp';
        Storage::disk('public')->put($path, $bytes) || throw new RuntimeException('Gambar profil gagal disimpan.');

        return $path;
    }

    public static function hapus(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
