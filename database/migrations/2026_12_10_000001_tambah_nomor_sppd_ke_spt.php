<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            // One SPPD number per SPT, filled by Bagian Umum (versi 1.1.4 K5); free text, not unique.
            $table->string('nomor_sppd', 100)->nullable()->after('nomor_lengkap');
        });
    }

    public function down(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->dropColumn('nomor_sppd');
        });
    }
};
