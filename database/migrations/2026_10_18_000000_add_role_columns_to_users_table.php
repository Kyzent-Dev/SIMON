<?php

use App\Enums\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('id');
            $table->string('role')->default(Role::Irban->value)->after('password');
            // No FK: the irban table arrives in Fase 2.
            $table->unsignedBigInteger('irban_id')->nullable()->after('role');
            $table->boolean('wajib_ganti_password')->default(false)->after('irban_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn(['username', 'role', 'irban_id', 'wajib_ganti_password']);
        });
    }
};
