<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Models\Pkpt;
use App\Models\PkptUsulan;
use App\Models\User;

/** Irban proposes changes to its own items of a Disahkan PKPT; Admin takes them into a Perubahan PKPT or closes them. */
class PkptUsulanPolicy
{
    public function create(User $user, Pkpt $pkpt): bool
    {
        return $user->role === Role::Irban && $user->irban_id !== null && $pkpt->status === StatusPkpt::Disahkan;
    }

    public function view(User $user, PkptUsulan $u): bool
    {
        return $user->role === Role::Admin || ($user->role === Role::Irban && (int) $user->irban_id === $u->irban_id);
    }

    public function tampung(User $user, PkptUsulan $u): bool
    {
        return $user->role === Role::Admin;
    }

    public function tutup(User $user, PkptUsulan $u): bool
    {
        return $user->role === Role::Admin;
    }
}
