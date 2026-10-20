<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Komentar;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** The only place that decides who receives a notification (Fase 6). */
class PenerimaNotifikasi
{
    /** @return Collection<int, User> */
    public function bagianUmum(): Collection
    {
        return User::where('role', Role::BagianUmum)->get();
    }

    /** @return Collection<int, User> */
    public function pimpinan(): Collection
    {
        return User::where('role', Role::Pimpinan)->get();
    }

    /** Parties of a comment thread: the SPT's irban users and every author in the thread, minus the author of $komentar. */
    public function utasKomentar(Komentar $komentar): Collection
    {
        $root = $komentar->parent ?? $komentar;
        $penulis = User::whereIn('id', $root->balasan()->pluck('user_id')->push($root->user_id));

        return $this->irban($komentar->spt->irban_id)->merge($penulis->get())->unique('id')->reject(fn (User $u) => $u->id === $komentar->user_id)->values();
    }

    /** @return Collection<int, User> */
    public function irban(int $irbanId): Collection
    {
        return User::where('role', Role::Irban)->where('irban_id', $irbanId)->get();
    }
}
