<?php

namespace App\Actions\Profil;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Profil "Data akun" of a personal account: name and username at once; a new email only after verification (MintaGantiEmail). Irban accounts: Admin only (403). */
class UbahDataAkun
{
    public function __construct(private MintaGantiEmail $gantiEmail) {}

    /** @return bool true when a verification link was sent to a new email */
    public function handle(User $user, array $data): bool
    {
        abort_if($user->role === Role::Irban, 403);
        $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);
        $valid = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'regex:/^[a-z0-9._]{3,30}$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'max:255', 'email:rfc'],
        ], [
            'username.regex' => 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.',
            'username.required' => 'Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.',
            'username.unique' => 'Username sudah dipakai.',
            'email.email' => 'Format email tidak valid.',
        ], ['name' => 'Nama tampilan', 'username' => 'Username', 'email' => 'Email'])->validate();

        // One transaction: a rejected new email leaves name and username unchanged too.
        return DB::transaction(function () use ($user, $valid) {
            $user->forceFill(['name' => $valid['name'], 'username' => $valid['username']])->save();

            return $this->gantiEmail->handle($user, $valid['email']);
        });
    }
}
