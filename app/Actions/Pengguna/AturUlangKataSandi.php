<?php

namespace App\Actions\Pengguna;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AturUlangKataSandi
{
    public function handle(User $admin, User $user, string $sementara): void
    {
        Gate::forUser($admin)->authorize('update', $user);
        Validator::make(['sementara' => $sementara], ['sementara' => ['required', 'string', Password::defaults()]], ['sementara.required' => 'Kata sandi sementara wajib diisi.'], ['sementara' => 'Kata sandi sementara'])->validate();

        $user->forceFill(['password' => $sementara, 'wajib_ganti_password' => true, 'remember_token' => Str::random(60)])->save();
    }
}
