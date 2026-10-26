<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;

/** Version-keyed cache of public aggregates (Fase 11 E6): bumping the version orphans every entry; old keys expire by TTL. */
class CachePublik
{
    private const TTL = 3600;

    public static function ingat(string $nama, array $argumen, Closure $hitung): mixed
    {
        self::urut($argumen);

        return Cache::remember('publik:'.self::versi().":{$nama}:".md5(json_encode($argumen)), self::TTL, $hitung);
    }

    public static function versi(): int
    {
        return (int) Cache::get('publik:versi', 1);
    }

    public static function segarkan(): void
    {
        Cache::forever('publik:versi', self::versi() + 1);
    }

    /** Same filter, any key order, one cache key. */
    private static function urut(array &$a): void
    {
        ksort($a);
        foreach ($a as &$v) {
            is_array($v) && self::urut($v);
        }
    }
}
