<?php

namespace Database\Seeders;

use App\Models\Irban;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(IrbanSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(TimSeeder::class);
        $this->call(ObjekSeeder::class);
        $this->call(IrbanWilayahSeeder::class);

        if (! app()->isProduction()) {
            // irban_id is not mass-assignable.
            User::where('username', 'irban1')->first()?->forceFill(['irban_id' => Irban::where('nama', 'Irban I')->value('id')])->save();
        }
    }
}
