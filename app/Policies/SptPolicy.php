<?php

namespace App\Policies;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;

class SptPolicy
{
    private function milik(User $user, Spt $spt): bool
    {
        return $user->role === Role::Irban && $user->irban_id !== null && $user->irban_id === $spt->irban_id;
    }

    /** Irban: own irban, any status. Bagian Umum, Pimpinan, Admin: anything that has been submitted (not draf). */
    public function view(User $user, Spt $spt): bool
    {
        if ($user->role === Role::Irban) {
            return $this->milik($user, $spt);
        }

        return $spt->status_pengajuan !== StatusPengajuan::Draf;
    }

    public function create(User $user): bool
    {
        return $user->role === Role::Irban && $user->irban_id !== null;
    }

    public function update(User $user, Spt $spt): bool
    {
        return $this->milik($user, $spt) && in_array($spt->status_pengajuan, [StatusPengajuan::Draf, StatusPengajuan::PerluRevisi], true);
    }

    /** Irban of the SPT, after the number is issued and until it is marked Selesai: dates, final SPT, Selesai, kendala. */
    public function laksanakan(User $user, Spt $spt): bool
    {
        return $this->milik($user, $spt) && $spt->status_pengajuan === StatusPengajuan::Diterima && $spt->ditandai_selesai_pada === null;
    }

    /** Terima / minta revisi / tolak a pending submission. */
    public function decide(User $user, Spt $spt): bool
    {
        return $user->role === Role::BagianUmum && $spt->status_pengajuan === StatusPengajuan::MenungguNomor;
    }

    /** Manual number entry, cancel number, register, starting number: Bagian Umum only. */
    public function kelolaNomor(User $user): bool
    {
        return $user->role === Role::BagianUmum;
    }
}
