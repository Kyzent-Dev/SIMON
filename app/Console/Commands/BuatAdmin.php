<?php

namespace App\Console\Commands;

use App\Actions\Pengguna\SimpanPengguna;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** First Admin of a fresh install (E11): same rules as SimpanPengguna, hidden password prompts, the password is never printed or logged. */
class BuatAdmin extends Command
{
    protected $signature = 'sintas:buat-admin {username} {--nama=} {--email=}';

    protected $description = 'Buat akun Admin (kata sandi ditanyakan tanpa ditampilkan).';

    public function handle(): int
    {
        $sandi = (string) $this->secret('Kata sandi');
        if (! hash_equals($sandi, (string) $this->secret('Ulangi kata sandi'))) {
            $this->error('Kata sandi tidak sama.');

            return self::FAILURE;
        }

        $data = [
            'name' => $this->option('nama') ?? 'Administrator', 'username' => $this->argument('username'),
            'email' => mb_strtolower(trim((string) $this->option('email'))), 'role' => Role::Admin->value, 'password' => $sandi,
        ];
        unset($sandi);
        $v = Validator::make($data, SimpanPengguna::rules(null, Role::Admin->value, $data['username']), SimpanPengguna::messages());
        if ($v->fails()) {
            foreach ($v->errors()->all() as $pesan) {
                $this->error($pesan);
            }

            return self::FAILURE;
        }

        // Admin chose this password at the console: no forced change at first login.
        (new User)->forceFill([
            'name' => trim($data['name']), 'username' => $data['username'], 'email' => mb_strtolower(trim($data['email'])),
            'role' => Role::Admin, 'password' => $data['password'], 'wajib_ganti_password' => false,
        ])->save();
        $this->info("Admin {$data['username']} dibuat.");

        return self::SUCCESS;
    }
}
