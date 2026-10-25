<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // No FK: the log outlives the pegawai master data.
            $table->unsignedBigInteger('diinput_oleh')->nullable()->index();
            $table->string('ip', 45)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['diinput_oleh']);
            $table->dropColumn(['diinput_oleh', 'ip']);
        });
    }
};
