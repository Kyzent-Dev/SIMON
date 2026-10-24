<?php

namespace App\Actions\Pengguna;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SimpanPengguna
{
    public static function rules(?User $user, string $role): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'regex:/^[a-z0-9._]{3,30}$/', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['required', 'string', 'max:255', 'email:rfc', Rule::unique('users', 'email')->ignore($user?->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'irban_id' => [Rule::requiredIf($role === Role::Irban->value), 'nullable', 'exists:irban,id'],
            'password' => $user ? ['nullable'] : ['required', 'string', Password::defaults()],
        ];
    }

    public static function messages(): array
    {
        return [
            'username.regex' => 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.',
            'username.required' => 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.',
            'username.unique' => 'Username sudah dipakai.',
            'email.unique' => 'Email sudah dipakai pengguna lain.',
            'email.email' => 'Format email tidak valid.',
            'irban_id.required' => 'Pilih irban untuk peran Irban.',
            'password.required' => 'Kata sandi sementara wajib diisi.',
        ];
    }

    public function handle(User $admin, ?User $user, array $data): User
    {
        $user ? Gate::forUser($admin)->authorize('update', $user) : Gate::forUser($admin)->authorize('create', User::class);

        $role = Role::from($data['role']);
        if ($user && $user->role === Role::Admin && $role !== Role::Admin) {
            if ($user->is($admin)) {
                throw ValidationException::withMessages(['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']);
            }
            if (User::where('role', Role::Admin)->whereKeyNot($user->id)->doesntExist()) {
                throw ValidationException::withMessages(['role' => 'Harus ada setidaknya satu Admin.']);
            }
        }
        if ($user && $user->is($admin) && $role !== $user->role) {
            throw ValidationException::withMessages(['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']);
        }

        $user ??= new User;
        $user->forceFill([
            'name' => trim($data['name']), 'username' => $data['username'], 'email' => mb_strtolower(trim($data['email'])),
            'role' => $role, 'irban_id' => $role === Role::Irban ? $data['irban_id'] : null,
        ]);
        if (! $user->exists) {
            $user->forceFill(['password' => $data['password'], 'wajib_ganti_password' => true]);
        }
        $user->save();

        return $user;
    }
}
