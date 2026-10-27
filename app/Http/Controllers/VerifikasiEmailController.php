<?php

namespace App\Http\Controllers;

use App\Actions\Profil\MintaGantiEmail;
use App\Models\User;
use App\Notifications\EmailDiubah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Signed link from VerifikasiEmailBaru (route `signed` rejects expired or forged links). Only the account itself, only for the still-pending `email_baru`. */
class VerifikasiEmailController extends Controller
{
    public function __invoke(Request $request, User $user, string $hash): RedirectResponse
    {
        abort_unless($request->user()->is($user), 403);
        $baru = $user->email_baru;
        $nonce = Cache::get(MintaGantiEmail::kunciNonce($user));
        if (! $baru || ! $nonce || ! hash_equals(MintaGantiEmail::hash($baru, $nonce), $hash) || User::where('email', $baru)->exists()) {
            return redirect()->route('profil')->with('toast', ['type' => 'error', 'message' => 'Tautan tidak berlaku atau sudah dipakai.']);
        }

        $lama = $user->email;
        $user->forceFill(['email' => $baru, 'email_baru' => null, 'email_verified_at' => now()])->save();
        Cache::forget(MintaGantiEmail::kunciNonce($user));
        Notification::route('mail', $lama)->notify(new EmailDiubah($user->username, $baru));

        return redirect()->route('profil')->with('toast', ['type' => 'success', 'message' => 'Email akun diubah.']);
    }
}
