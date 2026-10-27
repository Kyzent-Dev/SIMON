<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Replaces the child rows of a relation with $baru, deleting/creating only rows whose columns differ, so the activity log skips unchanged rows. */
class SinkronBaris
{
    /** @param list<array<string, mixed>> $baru every row has the same columns, in the same order */
    public static function handle(HasMany $relasi, array $baru): void
    {
        $kolom = array_keys($baru[0] ?? []);
        $kunci = fn (array $r) => json_encode(array_map(fn ($v) => $v instanceof BackedEnum ? $v->value : ($v === null ? null : (string) $v), $r));
        $sisa = array_map($kunci, $baru);

        // Deletes first: a unique key (tim_id, pegawai_id) must be free before its changed row is created.
        foreach ($relasi->get() as $model) {
            if (($i = array_search($kunci($model->only($kolom)), $sisa, true)) !== false) {
                unset($sisa[$i]);
            } else {
                $model->delete();
            }
        }
        foreach ($baru as $i => $r) {
            if (array_key_exists($i, $sisa)) {
                $relasi->create($r);
            }
        }
    }
}
