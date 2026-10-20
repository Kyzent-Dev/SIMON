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

    /** Write both variants for an upload (validated: jpg/png/webp, 960–6000 px) and return the new base path `slide/{uuid}`. Throws RuntimeException when GD cannot decode it. */
    public static function simpan(UploadedFile $file): string
    {
        // A 6000 x 6000 image decodes to ~144 MB in GD; the default 128M is too tight.
        if (self::byte(ini_get('memory_limit')) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        $src = @imagecreatefromstring($file->get()) ?: throw new RuntimeException('Gambar tidak dapat dibaca.');

        return self::tulis(self::tegakkan($src, $file));
    }

    /** Write both variants from a GD image (seeder) and return the base path. Always frees $src; on failure no variant is left behind. */
    public static function tulis(\GdImage $src): string
    {
        $base = 'slide/'.Str::uuid();
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

    /**
     * Apply the EXIF orientation of a JPEG (phone photos) before cropping. Needs the `exif` extension; without it the image is used as stored.
     * Mirrored orientations (2, 4, 5, 7) are rare from cameras and are left as is.
     */
    private static function tegakkan(\GdImage $src, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return $src;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($file->get()));
        $sudut = [3 => 180, 6 => 270, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($sudut === 0) {
            return $src;
        }
        $putar = imagerotate($src, $sudut, 0);
        imagedestroy($src);

        return $putar;
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
