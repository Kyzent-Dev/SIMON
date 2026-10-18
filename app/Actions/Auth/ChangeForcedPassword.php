<?php

namespace App\Actions\Auth;

use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class ChangeForcedPassword
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function __invoke(User $user, array $input): void
    {
        $validated = Validator::make($input, [
            'password' => [
                'required', 'string', 'confirmed', Password::defaults(),
                function (string $attribute, mixed $value, Closure $fail) use ($user) {
                    if (is_string($value) && Hash::check($value, $user->password)) {
                        $fail(__('passwords.different'));
                    }
                },
            ],
        ])->validate();

        $user->forceFill(['password' => $validated['password'], 'wajib_ganti_password' => false])->save();
    }
}
