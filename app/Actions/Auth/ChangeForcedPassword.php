<?php

namespace App\Actions\Auth;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangeForcedPassword
{
    /** New-password rules shared with the Profil password change: confirmed, Password::defaults(), different from the current one. */
    public static function aturan(User $user): array
    {
        return [
            'password' => [
                'required', 'string', 'confirmed', Password::defaults(),
                function (string $attribute, mixed $value, Closure $fail) use ($user) {
                    if (is_string($value) && Hash::check($value, $user->password)) {
                        $fail(__('passwords.different'));
                    }
                },
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): void
    {
        $validated = Validator::make($input, self::aturan($user))->validate();

        $user->forceFill(['password' => $validated['password'], 'wajib_ganti_password' => false, 'remember_token' => Str::random(60)])->save();
    }
}
