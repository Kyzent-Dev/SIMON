<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PkptPerubahan;
use App\Models\User;
use App\Services\PerubahanPkpt;

/** Admin prepares and manages SK, Pimpinan decides, Irban only reads packages that touch its irban. Status rules live in the actions. */
class PkptPerubahanPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Pimpinan], true);
    }

    public function view(User $user, PkptPerubahan $p): bool
    {
        if ($this->viewAny($user)) {
            return true;
        }

        return $user->role === Role::Irban && $user->irban_id !== null
            && in_array((int) $user->irban_id, app(PerubahanPkpt::class)->irbanTerdampak($p), true);
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Admin;
    }

    public function update(User $user, PkptPerubahan $p): bool
    {
        return $user->role === Role::Admin;
    }

    public function ajukan(User $user, PkptPerubahan $p): bool
    {
        return $user->role === Role::Admin;
    }

    public function buang(User $user, PkptPerubahan $p): bool
    {
        return $user->role === Role::Admin;
    }

    public function kelolaSk(User $user, PkptPerubahan $p): bool
    {
        return $user->role === Role::Admin;
    }

    public function putuskan(User $user, PkptPerubahan $p): bool
    {
        return $user->role === Role::Pimpinan;
    }

    public function unduhSk(User $user, PkptPerubahan $p): bool
    {
        return $p->berkas_sk !== null && $this->view($user, $p);
    }
}
