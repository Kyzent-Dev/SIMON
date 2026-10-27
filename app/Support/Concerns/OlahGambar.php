<?php

namespace App\Support\Concerns;

use App\Support\GambarTidakTerbaca;
use Illuminate\Http\UploadedFile;

/** Shared GD helpers for uploaded images (slides, profile pictures): decode with enough memory, EXIF orientation, WebP encoding. */
trait OlahGambar
{
    /** Decode an upload (already checked: readable header, at most 6000 x 6000 px) and turn it upright. Throws GambarTidakTerbaca when GD cannot decode it. Transparency is flattened onto white (WebP would otherwise turn it black). */
    protected static function dekode(UploadedFile $file): \GdImage
    {
        // A 6000 x 6000 image decodes to ~144 MB in GD; the default 128M is too tight.
        if (self::byte(ini_get('memory_limit')) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
        $bytes = $file->get();
        $src = @imagecreatefromstring($bytes) ?: throw new GambarTidakTerbaca('Gambar tidak dapat dibaca.');
        // Only non-JPEG formats (PNG, WebP) can carry alpha.
        if (! str_starts_with($bytes, "\xFF\xD8")) {
            $src = self::ratakan($src);
        }

        return self::tegakkan($src, $file);
    }

    /** Copy onto an opaque white canvas and free the original. */
    private static function ratakan(\GdImage $src): \GdImage
    {
        $out = imagecreatetruecolor(imagesx($src), imagesy($src));
        imagefill($out, 0, 0, (int) imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        imagedestroy($src);

        return $out;
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

    /** Width and height after turning the image by $sudut degrees (a quarter turn swaps them). @return array{0: int, 1: int} */
    public static function ukuranTegak(int $lebar, int $tinggi, int $sudut): array
    {
        return $sudut % 180 === 90 ? [$tinggi, $lebar] : [$lebar, $tinggi];
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
