<?php

namespace App\Actions\Profil;

use App\Models\User;
use App\Support\DiinputOleh;
use App\Support\GambarProfil;
use Illuminate\Support\Facades\Log;

/** "Hapus foto" / "Hapus latar": clear the column, then delete the file. Irban: "Diinput oleh" required and logged. */
class HapusGambarProfil
{
    public function handle(User $user, string $jenis, mixed $diinputOleh = null): void
    {
        $kolom = SimpanGambarProfil::kolom($jenis);
        $penginput = DiinputOleh::wajib($user, $diinputOleh);
        $lama = $user->{$kolom};
        if (! $lama) {
            return;
        }
        $user->forceFill([$kolom => null])->save();
        GambarProfil::hapus($lama);

        if ($penginput) {
            DiinputOleh::ingat($user, $penginput);
            // shortcut: Log facade until the Fase 10 activity log table exists; move to it then.
            Log::info("profil.hapus_{$jenis}", ['user_id' => $user->id, 'diinput_oleh' => $penginput]);
        }
    }
}
