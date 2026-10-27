<?php

namespace App\Actions\Profil;

use App\Actions\Pengguna\SimpanPengguna;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
            'username' => SimpanPengguna::aturanUsername($user, $data['username'] ?? null),
            'email' => ['required', 'string', 'max:255', 'email:rfc'],
        ], Arr::only(SimpanPengguna::messages(), ['username.regex', 'username.required', 'username.unique', 'email.email']), ['name' => 'Nama tampilan', 'username' => 'Username', 'email' => 'Email'])->validate();

        // One transaction: a rejected new email leaves name and username unchanged too.
        return DB::transaction(function () use ($user, $valid) {
            $user->forceFill(['name' => $valid['name'], 'username' => $valid['username']])->save();

            return $this->gantiEmail->handle($user, $valid['email']);
        });
    }
}
