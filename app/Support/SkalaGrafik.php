<?php

namespace App\Support;

/** Axis scale and pixel heights for the vertical PDF bar charts (dompdf has no JS, so PHP does the maths; Blade only loops and echoes). */
final class SkalaGrafik
{
    public const TINGGI = 140;

    private const LANGKAH = [1, 2, 5];

    private const MAKS_INTERVAL = 5;

    /** "Nice" ascending ticks from 0 covering $maks in at most 5 intervals (7 -> 0,2,4,6,8). All zero gives 0..4. */
    public static function tick(int $maks): array
    {
        if ($maks <= 0) {
            return [0, 1, 2, 3, 4];
        }
        for ($pangkat = 1; ; $pangkat *= 10) {
            foreach (self::LANGKAH as $k) {
                $langkah = $k * $pangkat;
                if (intdiv($maks + $langkah - 1, $langkah) <= self::MAKS_INTERVAL) {
                    return range(0, intdiv($maks + $langkah - 1, $langkah) * $langkah, $langkah);
                }
            }
        }
    }

    /**
     * Pixel heights of one category's bars. $tumpuk: segments are cumulative slices of one stack (heights sum to the stack's height); otherwise independent bars.
     * A non-zero value never rounds to 0 px; nothing exceeds $tinggi.
     *
     * @param  list<int>  $nilai
     * @return list<int>
     */
    public static function batang(array $nilai, int $puncak, bool $tumpuk, int $tinggi = self::TINGGI): array
    {
        $px = fn (int $n) => $n <= 0 || $puncak <= 0 ? 0 : min($tinggi, max(2, (int) round($n / $puncak * $tinggi)));
        if (! $tumpuk) {
            return array_map($px, $nilai);
        }
        $hasil = [];
        $kum = 0;
        $sebelum = 0;
        foreach ($nilai as $n) {
            $kum += $n;
            $sekarang = $n > 0 ? max($sebelum + 2, $px($kum)) : $sebelum;
            $hasil[] = min($tinggi, $sekarang) - $sebelum;
            $sebelum += end($hasil);
        }

        return $hasil;
    }

    /** Gridline offsets from the bottom, in px, per tick. */
    public static function garis(array $tick, int $tinggi = self::TINGGI): array
    {
        $atas = max(end($tick), 1);

        return array_map(fn ($t) => ['nilai' => $t, 'bawah' => (int) round($t / $atas * $tinggi)], $tick);
    }

    /** Plot width in % of the page block: few categories get a narrower, centred chart. */
    public static function lebar(int $kategori): int
    {
        return match (true) {
            $kategori <= 1 => 40,
            $kategori === 2 => 60,
            $kategori === 3 => 80,
            default => 100,
        };
    }
}
