<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;

class PkptItemRevisiPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [Role::Admin, Role::Pimpinan, Role::Irban], true);
    }

    /** Irban account of the item's own irban, and the PKPT is Disahkan. */
    public function create(User $user, PkptItem $item): bool
    {
        return $user->role === Role::Irban && $user->irban_id && $user->irban_id === $item->irban_id
            && $item->pkpt->status === StatusPkpt::Disahkan;
    }

    public function decide(User $user, PkptItemRevisi $revisi): bool
    {
        return $user->role === Role::Pimpinan;
    }
}
