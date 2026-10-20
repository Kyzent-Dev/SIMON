<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public aggregates (RealisasiPublik::basis) filter on these columns. No extra spt_objek(objek_id) index: on MySQL the
 * objek_id foreign key already has its auto-created index (the unique (spt_id, objek_id) cannot serve it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->index(['status_pengajuan', 'klasifikasi_id', 'pkpt_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->dropIndex(['status_pengajuan', 'klasifikasi_id', 'pkpt_item_id']);
        });
    }
};
