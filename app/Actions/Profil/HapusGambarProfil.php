<?php

namespace App\Actions\Profil;

use App\Models\User;
use App\Support\DiinputOleh;
use App\Support\GambarProfil;

/** "Hapus foto" / "Hapus latar": clear the column, then delete the file. Irban: "Diinput oleh" required and logged. */
class HapusGambarProfil
{
    public function handle(User $user, string $jenis, mixed $diinputOleh = null): void
    {
        $kolom = SimpanGambarProfil::kolom($jenis);
        $penginput = DiinputOleh::wajib($user, $diinputOleh);
        DiinputOleh::catat($penginput);
        $lama = $user->{$kolom};
        if (! $lama) {
            return;
        }
        $user->forceFill([$kolom => null])->save();
        GambarProfil::hapus($lama);

        if ($penginput) {
            DiinputOleh::ingat($user, $penginput);
        }
        activity()->performedOn($user)->causedBy($user)->event("profil.hapus_{$jenis}")->log("profil.hapus_{$jenis}");
    }
}
