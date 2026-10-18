<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttemptLogin
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    /**
     * Credential errors go under the `login` key so the view shows one generic alert, never a field hint.
     *
     * @throws ValidationException
     */
    public function __invoke(string $username, string $password, bool $remember, string $ip): void
    {
        $key = Str::lower($username).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            session()->flash('lockout_seconds', $seconds);

            throw ValidationException::withMessages(['login' => __('auth.throttle', ['seconds' => $seconds])]);
        }

        if (! Auth::attempt(['username' => $username, 'password' => $password], $remember)) {
            RateLimiter::hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        RateLimiter::clear($key);
    }
}
