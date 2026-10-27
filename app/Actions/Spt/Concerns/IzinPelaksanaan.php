<?php

namespace App\Actions\Spt\Concerns;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** The `laksanakan` check shared by the Irban actions (through MelaksanakanSpt) and Spt\Detail (before opening a modal). */
trait IzinPelaksanaan
{
    /** The owning Irban gets the Selesai message (toastable, key `spt`) instead of a bare 403; everyone else is just denied. */
    private function izinkan(User $user, Spt $spt): void
    {
        if ($spt->status_pengajuan === StatusPengajuan::Diterima && $spt->ditandai_selesai_pada !== null
            && $user->role === Role::Irban && $user->irban_id === $spt->irban_id) {
            throw ValidationException::withMessages(['spt' => 'SPT ini sudah Selesai dan tidak dapat diubah.']);
        }
        Gate::forUser($user)->authorize('laksanakan', $spt);
    }
}
