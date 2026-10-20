<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Idempotency of `notifikasi:harian`: each SPT gets the Terlambat and SPT-final reminders at most once. */
    public function up(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->timestamp('terlambat_diberitahu_pada')->nullable();
            $table->timestamp('pengingat_final_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->dropColumn(['terlambat_diberitahu_pada', 'pengingat_final_pada']);
        });
    }
};
