<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Hero slide images (DESIGN §6 Imagery): center-crop to 16:9, write WebP 1920 and 960 variants on the public disk with GD (no package). */
class GambarSlide
{
    public const LEBAR = [1920, 960];

    private const MAKS_BYTE = 250 * 1024;

    /** Write both variants for an upload (already validated: jpg/png/webp, ≥960 wide) and return the new base path `slide/{uuid}`. */
    public static function simpan(UploadedFile $file): string
    {
        // A 5 MB JPEG can decode to ~25 MP (~100 MB in GD); the default 128M is too tight.
        if (self::byte(ini_get('memory_limit')) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        $src = @imagecreatefromstring($file->get()) ?: throw new RuntimeException('Gambar tidak dapat dibaca.');

        return self::tulis($src);
    }

    /** Write both variants from a GD image (seeder) and return the base path. */
    public static function tulis(\GdImage $src): string
    {
        $w = imagesx($src);
        $h = imagesy($src);
        // Center crop to 16:9 without an intermediate copy: resample straight from the source region.
        [$cw, $ch] = $w * 9 > $h * 16 ? [(int) round($h * 16 / 9), $h] : [$w, (int) round($w * 9 / 16)];
        $cx = intdiv($w - $cw, 2);
        $cy = intdiv($h - $ch, 2);

        $base = 'slide/'.Str::uuid();
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
            Storage::disk('public')->put("{$base}-{$target}.webp", $bytes);
        }
        imagedestroy($src);

        return $base;
    }

    public static function hapus(?string $base): void
    {
        if ($base) {
            Storage::disk('public')->delete(array_map(fn ($l) => "{$base}-{$l}.webp", self::LEBAR));
        }
    }

    private static function webp(\GdImage $img, int $kualitas): string
    {
        ob_start();
        imagewebp($img, null, $kualitas);

        return (string) ob_get_clean();
    }

    private static function byte(string|false $v): int
    {
        $v = trim((string) $v);
        if ($v === '' || $v === '-1') {
            return PHP_INT_MAX;
        }

        return (int) $v * match (strtoupper(substr($v, -1))) {
            'G' => 1024 ** 3, 'M' => 1024 ** 2, 'K' => 1024, default => 1
        };
    }
}
