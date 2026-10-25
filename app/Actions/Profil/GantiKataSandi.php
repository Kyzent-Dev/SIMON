<?php

namespace App\Actions\Profil;

use App\Actions\Auth\ChangeForcedPassword;
use App\Models\Pegawai;
use App\Models\User;
use App\Notifications\KataSandiDiubah;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/** Profil password change by the signed-in user: old password, the forced-change rules, then every other session is logged out (AuthenticateSession) and the account is mailed. */
class GantiKataSandi
{
    /** @param array{password_lama?: mixed, password?: mixed, password_confirmation?: mixed} $input */
    public function handle(User $user, array $input, mixed $diinputOleh = null): void
    {
        abort_unless($user->is(Auth::user()), 403);
        $valid = Validator::make($input, ['password_lama' => ['required', 'string', 'current_password']] + ChangeForcedPassword::aturan($user),
            ['password_lama.current_password' => 'Kata sandi lama salah.'],
            ['password_lama' => 'Kata sandi lama', 'password' => 'Kata sandi baru'])->validate();
        $penginput = DiinputOleh::wajib($user, $diinputOleh);
        DiinputOleh::catat($penginput);

        $user->forceFill(['password' => $valid['password']])->save();
        // Rehashes once more and refreshes this device's remember cookie; AuthenticateSession stores this session's new hash after the request.
        Auth::logoutOtherDevices($valid['password']);

        if ($penginput) {
            DiinputOleh::ingat($user, $penginput);
        }
        activity()->performedOn($user)->causedBy($user)->event('profil.kata_sandi')->log('profil.kata_sandi');
        $user->notify(new KataSandiDiubah($user->username, $penginput ? Pegawai::find($penginput)?->nama_bergelar : null));
    }
}
