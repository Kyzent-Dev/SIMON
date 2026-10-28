<?php

namespace App\Support;

use App\Support\Concerns\OlahGambar;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Hero slide images (DESIGN §6 Imagery): center-crop to 16:9, write WebP 1920 and 960 variants on the public disk with GD (no package). */
class GambarSlide
{
    use OlahGambar;

    public const LEBAR = [1920, 960];

    private const MAKS_BYTE = 250 * 1024;

    /** Write both variants for an upload (validated: jpg/png/webp, 960–6000 px) and return the new base path `{folder}/{uuid}` (folder `slide`, or `login` for the Masuk background). Throws RuntimeException when GD cannot decode it. */
    public static function simpan(UploadedFile $file, string $folder = 'slide'): string
    {
        return self::tulis(self::dekode($file), $folder);
    }

    /** Write both variants from a GD image (seeder) and return the base path. Always frees $src; on failure no variant is left behind. */
    public static function tulis(\GdImage $src, string $folder = 'slide'): string
    {
        $base = $folder.'/'.Str::uuid();
        try {
            $w = imagesx($src);
            $h = imagesy($src);
            // Center crop to 16:9 without an intermediate copy: resample straight from the source region.
            [$cw, $ch] = $w * 9 > $h * 16 ? [(int) round($h * 16 / 9), $h] : [$w, (int) round($w * 9 / 16)];
            $cx = intdiv($w - $cw, 2);
            $cy = intdiv($h - $ch, 2);

            foreach (self::LEBAR as $target) {
                $tw = min($target, $cw);
                $th = (int) round($tw * 9 / 16);
                $out = imagecreatetruecolor($tw, $th);
                imagecopyresampled($out, $src, 0, 0, $cx, $cy, $tw, $th, $cw, $ch);
                $kualitas = 80;
                do {
                    $bytes = self::webp($out, $kualitas);
                    $kualitas -= 10;
                } while ($target === 1920 && strlen($bytes) > self::MAKS_BYTE && $kualitas >= 50);
                imagedestroy($out);
                Storage::disk('public')->put("{$base}-{$target}.webp", $bytes) || throw new RuntimeException('Gambar slide gagal disimpan.');
            }
        } catch (\Throwable $e) {
            self::hapus($base);
            throw $e;
        } finally {
            imagedestroy($src);
        }

        return $base;
    }

    public static function hapus(?string $base): void
    {
        if ($base) {
            Storage::disk('public')->delete(array_map(fn ($l) => "{$base}-{$l}.webp", self::LEBAR));
        }
    }
}
