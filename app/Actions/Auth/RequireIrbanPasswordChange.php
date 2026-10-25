<?php

namespace App\Actions\Auth;

use App\Enums\Role;
use App\Models\User;

class RequireIrbanPasswordChange
{
    /** Flags every Irban account for a forced password change; returns how many were flagged. */
    public function __invoke(): int
    {
        // Per model so each flagged account is in the activity log.
        return User::where('role', Role::Irban)->get()
            ->each(fn (User $u) => $u->forceFill(['wajib_ganti_password' => true, 'remember_token' => null])->save())->count();
    }
}
