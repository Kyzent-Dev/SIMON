<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Public aggregates (RealisasiPublik::basis) filter on these; spt_objek(objek_id) serves the wilayah filter. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->index(['status_pengajuan', 'klasifikasi_id', 'pkpt_item_id']);
        });
        Schema::table('spt_objek', function (Blueprint $table) {
            $table->index('objek_id');
        });
    }

    public function down(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->dropIndex(['status_pengajuan', 'klasifikasi_id', 'pkpt_item_id']);
        });
        Schema::table('spt_objek', function (Blueprint $table) {
            $table->dropIndex(['objek_id']);
        });
    }
};
