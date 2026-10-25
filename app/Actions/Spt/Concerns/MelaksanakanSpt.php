<?php

namespace App\Actions\Spt\Concerns;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Shared guard of the Irban actions after a number is issued (Fase 5): `laksanakan` ability, row lock, "Diinput oleh". */
trait MelaksanakanSpt
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

    /** Validates "Diinput oleh" into $errors and returns the id. */
    private function penginput(User $user, array $data, array &$errors): ?int
    {
        $id = filter_var($data['diinput_oleh'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($galat = DiinputOleh::galat($user, $id)) {
            $errors['diinput_oleh'] = $galat;
        } else {
            DiinputOleh::catat($id);
        }

        return $id;
    }

    /** Re-read under lock and re-check the ability, so two tabs cannot both act. */
    private function kunci(User $user, Spt $spt): Spt
    {
        $spt = Spt::whereKey($spt->id)->lockForUpdate()->firstOrFail();
        $this->izinkan($user, $spt);

        return $spt;
    }
}
