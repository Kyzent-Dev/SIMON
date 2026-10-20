<?php

namespace App\Services;

use App\Enums\Role;
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

    /** @return Collection<int, User> */
    public function irban(int $irbanId): Collection
    {
        return User::where('role', Role::Irban)->where('irban_id', $irbanId)->get();
    }
}
