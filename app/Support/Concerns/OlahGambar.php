<?php

namespace App\Support\Concerns;

use Illuminate\Http\UploadedFile;
use RuntimeException;

/** Shared GD helpers for uploaded images (slides, profile pictures): decode with enough memory, EXIF orientation, WebP encoding. */
trait OlahGambar
{
    /** Decode an upload (already checked: readable header, at most 6000 x 6000 px) and turn it upright. Throws RuntimeException when GD cannot decode it. */
    protected static function dekode(UploadedFile $file): \GdImage
    {
        // A 6000 x 6000 image decodes to ~144 MB in GD; the default 128M is too tight.
        if (self::byte(ini_get('memory_limit')) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        $src = @imagecreatefromstring($file->get()) ?: throw new RuntimeException('Gambar tidak dapat dibaca.');

        return self::tegakkan($src, $file);
    }

    /**
     * Clockwise-to-GD rotation for the EXIF orientation of a JPEG (phone photos). Needs the `exif` extension; without it 0 (used as stored).
     * Mirrored orientations (2, 4, 5, 7) are rare from cameras and are left as is.
     */
    public static function sudutExif(UploadedFile $file): int
    {
        if (! function_exists('exif_read_data') || ! in_array(strtolower($file->getClientOriginalExtension()), ['jpg', 'jpeg'], true)) {
            return 0;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($file->get()));

        return [3 => 180, 6 => 270, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
    }

    /** Apply the EXIF orientation before cropping. */
    private static function tegakkan(\GdImage $src, UploadedFile $file): \GdImage
    {
        $sudut = self::sudutExif($file);
        if ($sudut === 0) {
            return $src;
        }
        $putar = imagerotate($src, $sudut, 0);
        imagedestroy($src);

        return $putar;
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
