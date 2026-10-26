<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 11, Task 3 (EXPLAIN in decisions/fase-11.md): monitoring, dashboard cards and the year lists filter accepted SPT by tahun.
 * Without it MySQL reads every accepted SPT of all years through (status_pengajuan, klasifikasi_id, pkpt_item_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->index(['status_pengajuan', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->dropIndex(['status_pengajuan', 'tahun']);
        });
    }
};
