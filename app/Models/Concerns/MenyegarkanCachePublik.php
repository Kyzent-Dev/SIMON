<?php

namespace App\Models\Concerns;

use App\Support\CachePublik;
use Illuminate\Support\Facades\DB;

/** Any save/delete of a model behind the public pages invalidates the public aggregate cache once the transaction commits. */
trait MenyegarkanCachePublik
{
    public static function bootMenyegarkanCachePublik(): void
    {
        $segarkan = fn () => DB::afterCommit(fn () => CachePublik::segarkan());
        static::saved($segarkan);
        static::deleted($segarkan);
    }
}
