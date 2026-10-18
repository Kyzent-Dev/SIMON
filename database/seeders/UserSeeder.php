<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

// Dev only: every account uses the password "password".
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['admin', 'Administrator', Role::Admin],
            ['pimpinan', 'Inspektur', Role::Pimpinan],
            ['irban1', 'Irban I', Role::Irban],
            ['bagianumum', 'Bagian Umum', Role::BagianUmum],
        ];

        foreach ($accounts as [$username, $name, $role]) {
            User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'email' => "{$username}@simon-spt.test",
                'password' => 'password',
                'role' => $role,
            ]);
        }
    }
}
