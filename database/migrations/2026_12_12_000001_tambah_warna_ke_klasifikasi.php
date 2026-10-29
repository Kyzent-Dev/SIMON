<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Ribbon/legend colour of the Rekap Tahunan workbook (spec 1.1.4 Task 4), as in the Bagian Umum file. Hex without '#'. */
    private const WARNA = ['LAIN_LAIN' => 'FFE699', 'KHUSUS' => 'F4B084', 'REGULER' => 'BDD7EE', 'DIKLAT' => 'A9D08E', 'LUAR_DAERAH' => 'FF99FF'];

    public function up(): void
    {
        Schema::table('klasifikasi', function (Blueprint $table) {
            $table->char('warna', 6)->nullable()->after('tampil_publik');
        });
        foreach (self::WARNA as $kode => $warna) {
            DB::table('klasifikasi')->where('kode', $kode)->update(['warna' => $warna]);
        }
    }

    public function down(): void
    {
        Schema::table('klasifikasi', function (Blueprint $table) {
            $table->dropColumn('warna');
        });
    }
};
