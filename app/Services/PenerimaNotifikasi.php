<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Komentar;
use App\Models\Pegawai;
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

    /** Only the requester of an export. @return Collection<int, User> */
    public function pengguna(User $user): Collection
    {
        return User::whereKey($user->id)->get();
    }

    /** The pegawai's own address, unless inactive, empty, or already covered by an account address that got the same notification. */
    public function emailPegawai(?Pegawai $p, Collection $akun): ?string
    {
        if (! $p?->aktif || blank($p->email)) {
            return null;
        }
        $sudah = $akun->flatMap(fn (User $u) => $u->routeNotificationForMail() ?? [])->map(fn ($a) => mb_strtolower($a));

        return $sudah->contains(mb_strtolower($p->email)) ? null : $p->email;
    }

    /** @return Collection<int, User> */
    public function irban(int $irbanId): Collection
    {
        return User::where('role', Role::Irban)->where('irban_id', $irbanId)->get();
    }
}
