<?php

namespace App\Actions\Pkpt\Usulan;

use App\Enums\StatusUsulan;
use App\Models\PkptUsulan;
use App\Models\User;
use App\Notifications\UsulanPerubahanDiputuskan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Admin closes an open usulan without applying it; the catatan tells the irban why. */
class TutupUsulan
{
    public function handle(User $user, PkptUsulan $u, string $catatan): PkptUsulan
    {
        Gate::forUser($user)->authorize('tutup', $u);

        $u = DB::transaction(function () use ($user, $u, $catatan) {
            $u = PkptUsulan::whereKey($u->id)->lockForUpdate()->first();
            if ($u?->status !== StatusUsulan::Terbuka) {
                throw ValidationException::withMessages(['usulan' => TampungUsulan::TIDAK_TERBUKA]);
            }
            $catatan = trim($catatan);
            if (mb_strlen($catatan) < 10) {
                throw ValidationException::withMessages(['catatan_penutupan' => $catatan === ''
                    ? 'Catatan penutupan wajib diisi.' : 'Catatan penutupan minimal 10 karakter.']);
            }
            if (mb_strlen($catatan) > 1000) {
                throw ValidationException::withMessages(['catatan_penutupan' => 'Catatan penutupan maksimal 1000 karakter.']);
            }
            $u->forceFill(['status' => StatusUsulan::Ditutup, 'ditutup_oleh' => $user->id, 'catatan_penutupan' => $catatan])->save();

            return $u;
        });

        UsulanPerubahanDiputuskan::kirim($u);

        return $u;
    }
}
