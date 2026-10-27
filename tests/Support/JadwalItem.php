<?php

namespace Tests\Support;

use App\Models\PkptItem;

/** Test helper for PKPT item dates after pengesahan. */
class JadwalItem
{
    /**
     * The item as an approved Perubahan PKPT leaves it: the pengesahan snapshot stays (taken from the current dates only when the
     * fixture never set one, like the factory's disahkanDari()), and only the dates move. So the item reads as "direvisi".
     */
    public static function geser(PkptItem $item, string $mulai, string $selesai): PkptItem
    {
        $item->forceFill([
            'tanggal_mulai_disahkan' => $item->tanggal_mulai_disahkan ?? $item->tanggal_mulai,
            'tanggal_selesai_disahkan' => $item->tanggal_selesai_disahkan ?? $item->tanggal_selesai,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
        ])->save();

        return $item;
    }
}
