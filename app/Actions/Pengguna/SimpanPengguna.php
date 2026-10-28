<?php

namespace App\Actions\Pengguna;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class SimpanPengguna
{
    /** @var string the one username format; also used by Profil "Data akun" (UbahDataAkun). */
    public const USERNAME_REGEX = '/^[a-z0-9._]{3,30}$/';

    /** The format is enforced only when the username changes, so a legacy username can still be edited around. */
    public static function aturanUsername(?User $user, ?string $username): array
    {
        $diubah = ! $user || $user->username !== $username;

        return array_values(array_filter(['required', $diubah ? 'regex:'.self::USERNAME_REGEX : null, Rule::unique('users', 'username')->ignore($user?->id)]));
    }

    /** $username is the submitted value (null on create). */
    public static function rules(?User $user, string $role, ?string $username = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => self::aturanUsername($user, $username),
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
        $izin = fn (?User $aktor) => $user ? Gate::forUser($aktor)->authorize('update', $user) : Gate::forUser($aktor)->authorize('create', User::class);
        $izin($admin);

        $role = Role::from($data['role']);

        // One lock, by id, on every admin row (target included) and the actor's row: two concurrent demotions serialize instead of
        // deadlocking, and the actor is re-authorized from its current row (an admin demoted or deleted meanwhile is refused).
        return DB::transaction(function () use ($admin, $user, $role, $data, $izin) {
            $terkunci = User::where('role', Role::Admin)->orWhereKey($admin->id)->orderBy('id')->lockForUpdate()->get(['id', 'role']);
            $aktor = $terkunci->firstWhere('id', $admin->id);
            $aktor ? $izin($aktor) : throw new AuthorizationException;

            if ($user && $role !== $user->role) {
                if ($user->is($admin)) {
                    throw ValidationException::withMessages(['role' => 'Anda tidak dapat mengubah peran akun Anda sendiri.']);
                }
                if ($user->role === Role::Admin && $terkunci->where('role', Role::Admin)->pluck('id')->reject($user->id)->isEmpty()) {
                    throw ValidationException::withMessages(['role' => 'Harus ada setidaknya satu Admin.']);
                }
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
        });
    }
}
