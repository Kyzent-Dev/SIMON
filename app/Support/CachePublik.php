<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Version-keyed cache of public aggregates (Fase 11 E6): bumping the version orphans every entry. Redis drops old keys by TTL;
 * the database store deletes an expired row only when that key is read again, so pangkasKedaluwarsa() runs nightly.
 */
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

    /** Deletes expired rows of the database cache store (orphaned versions, throttle:publik counters per IP); no-op for other stores. */
    public static function pangkasKedaluwarsa(): void
    {
        if (config('cache.default') !== 'database') {
            return;
        }
        $c = config('cache.stores.database');
        $db = DB::connection($c['connection'] ?? null);
        foreach ([$c['table'] ?? 'cache', $c['lock_table'] ?? 'cache_locks'] as $tabel) {
            $db->table($tabel)->where('expiration', '<=', now()->getTimestamp())->delete();
        }
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
