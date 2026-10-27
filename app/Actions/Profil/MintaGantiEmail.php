<?php

namespace App\Actions\Profil;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\VerifikasiEmailBaru;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Store `email_baru` and mail a signed 60-minute link to it; the old email stays active until the link is opened. The current email again = cancel a pending change. */
class MintaGantiEmail
{
    public static function kunciNonce(User $user): string
    {
        return 'ganti-email-nonce:'.$user->id;
    }

    public static function hash(string $email, string $nonce): string
    {
        return sha1($email.'|'.$nonce);
    }

    /** @return bool true when a link was sent */
    public function handle(User $user, string $email): bool
    {
        abort_if($user->role === Role::Irban, 403);
        $baru = mb_strtolower(trim($email));
        if ($baru === mb_strtolower($user->email)) {
            $user->forceFill(['email_baru' => null])->save();
            Cache::forget(self::kunciNonce($user));

            return false;
        }
        if ($baru === $user->email_baru) {
            return false;
        }
        Validator::make(['email' => $baru], [
            'email' => ['required', 'string', 'max:255', 'email:rfc',
                Rule::unique('users', 'email'), Rule::unique('users', 'email_baru')->ignore($user->id)],
        ], [
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'email.email' => 'Format email tidak valid.',
        ], ['email' => 'Email'])->validate();

        // Only real attempts count: cancelling or repeating the pending address above never reaches this point.
        $kunci = 'ganti-email:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 3)) {
            throw ValidationException::withMessages(['email' => 'Terlalu banyak permintaan. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.']);
        }
        RateLimiter::hit($kunci, 600);

        $user->forceFill(['email_baru' => $baru])->save();
        // A fresh nonce per request: a link from an earlier request (X, then Y, then X again) no longer matches.
        $nonce = Str::random(40);
        Cache::put(self::kunciNonce($user), $nonce, now()->addMinutes(60));
        $tautan = URL::temporarySignedRoute('profil.email.verifikasi', now()->addMinutes(60), ['user' => $user->id, 'hash' => self::hash($baru, $nonce)]);
        Notification::route('mail', $baru)->notify(new VerifikasiEmailBaru($user->username, $tautan));

        return true;
    }
}
