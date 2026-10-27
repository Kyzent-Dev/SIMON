<?php

namespace App\Actions\Spt\Concerns;

use App\Models\Spt;
use App\Models\User;
use App\Support\DiinputOleh;

/** Shared guard of the Irban actions after a number is issued (Fase 5): `laksanakan` ability, row lock, "Diinput oleh". */
trait MelaksanakanSpt
{
    use IzinPelaksanaan;

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
