<?php

namespace App\Support;

use App\Models\User;

/** Read/mark-read logic shared by the list page and the header bell. Ids always go through the user's own relation. */
class NotifikasiPengguna
{
    /** @param  list<string>  $ids  @return int rows newly marked read */
    public static function tandaiDibaca(User $user, array $ids): int
    {
        return $user->unreadNotifications()->whereIn('id', array_filter($ids, 'is_string'))->update(['read_at' => now()]);
    }

    public static function tandaiSemua(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /** Marks one of the user's notifications read and returns its relative url (null when it is not theirs). */
    public static function buka(User $user, string $id): ?string
    {
        $n = $user->notifications()->find($id);
        if (! $n) {
            return null;
        }
        self::tandaiDibaca($user, [$n->id]);

        return $n->data['url'] ?? null;
    }
}
