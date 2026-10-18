<?php

namespace App\Actions\Auth;

use App\Enums\Role;
use App\Models\User;

class RequireIrbanPasswordChange
{
    /** Flags every Irban account for a forced password change; returns how many were flagged. */
    public function __invoke(): int
    {
        return User::where('role', Role::Irban)->update(['wajib_ganti_password' => true]);
    }
}
