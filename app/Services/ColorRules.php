<?php

namespace App\Services;

/** Pure color math for the assignment-type picker (DESIGN §3.7). */
class ColorRules
{
    public const WHITE = '#FFFFFF';

    public const DARK = '#151C1A'; // neutral-900

    /** Mint-700 and success-700: a custom color must stay ΔE76 >= 10 away from both. */
    private const RESERVED = ['#17735B', '#1D6B34'];

    /** Labels are the ones stated in DESIGN §3.7 (they equal labelFor() for every swatch). */
    public const SWATCHES = [
        ['id' => 1, 'nama' => 'Nila', 'hex' => '#332288', 'label' => self::WHITE],
        ['id' => 2, 'nama' => 'Jingga', 'hex' => '#C77C00', 'label' => self::DARK],
        ['id' => 3, 'nama' => 'Biru Baja', 'hex' => '#3F6FA6', 'label' => self::WHITE],
        ['id' => 4, 'nama' => 'Mawar', 'hex' => '#CC6677', 'label' => self::DARK],
        ['id' => 5, 'nama' => 'Sian Tua', 'hex' => '#146C8F', 'label' => self::WHITE],
        ['id' => 6, 'nama' => 'Oker', 'hex' => '#A68A1F', 'label' => self::DARK],
        ['id' => 7, 'nama' => 'Ungu', 'hex' => '#AA4499', 'label' => self::WHITE],
        ['id' => 8, 'nama' => 'Bata', 'hex' => '#B84E00', 'label' => self::WHITE],
        ['id' => 9, 'nama' => 'Zaitun', 'hex' => '#8A8A2E', 'label' => self::DARK],
        ['id' => 10, 'nama' => 'Anggur', 'hex' => '#882255', 'label' => self::WHITE],
        ['id' => 11, 'nama' => 'Plum', 'hex' => '#6A3D9A', 'label' => self::WHITE],
        ['id' => 12, 'nama' => 'Cokelat (logo)', 'hex' => '#7A4E2D', 'label' => self::WHITE],
    ];

    /** '#abc' / 'aabbcc' -> '#AABBCC'; null when not a hex color. */
    public static function normalize(string $hex): ?string
    {
        $h = ltrim(trim($hex), '#');
        if (preg_match('/^[0-9a-f]{3}$/i', $h)) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $h) ? '#'.strtoupper($h) : null;
    }

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)) / 255, hexdec(substr($h, 2, 2)) / 255, hexdec(substr($h, 4, 2)) / 255];
    }

    private static function linear(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(self::linear(...), self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private static function lab(string $hex): array
    {
        [$r, $g, $b] = array_map(self::linear(...), self::rgb($hex));
        $xyz = [
            (0.4124564 * $r + 0.3575761 * $g + 0.1804375 * $b) / 0.95047,
            (0.2126729 * $r + 0.7151522 * $g + 0.0721750 * $b),
            (0.0193339 * $r + 0.1191920 * $g + 0.9503041 * $b) / 1.08883,
        ];
        [$fx, $fy, $fz] = array_map(fn ($t) => $t > 216 / 24389 ? $t ** (1 / 3) : (24389 / 27 * $t + 16) / 116, $xyz);

        return [116 * $fy - 16, 500 * ($fx - $fy), 200 * ($fy - $fz)];
    }

    public static function deltaE76(string $a, string $b): float
    {
        return sqrt(array_sum(array_map(fn ($x, $y) => ($x - $y) ** 2, self::lab($a), self::lab($b))));
    }

    /** White or neutral-900, whichever contrasts more and reaches 4.5; null when neither does. */
    public static function labelFor(string $hex): ?string
    {
        $label = self::contrast($hex, self::WHITE) >= self::contrast($hex, self::DARK) ? self::WHITE : self::DARK;

        return self::contrast($hex, $label) >= 4.5 ? $label : null;
    }

    /** @return list<string> error messages; empty = accepted. */
    public static function validateCustom(string $hex): array
    {
        $hex = self::normalize($hex);
        if ($hex === null) {
            return ['Masukkan warna heksadesimal, mis. #3F6FA6.'];
        }
        $errors = [];
        if (self::contrast($hex, self::WHITE) < 3) {
            $errors[] = 'Kontras warna terhadap putih minimal 3:1.';
        }
        foreach (self::RESERVED as $r) {
            if (self::deltaE76($hex, $r) < 10) {
                $errors[] = 'Warna terlalu mirip dengan warna utama/Selesai.';
                break;
            }
        }
        if (self::labelFor($hex) === null) {
            $errors[] = 'Warna terlalu terang/gelap untuk label teks';
        }

        return $errors;
    }
}
