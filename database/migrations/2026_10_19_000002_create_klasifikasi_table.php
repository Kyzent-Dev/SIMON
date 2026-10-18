<?php

use App\Enums\SyaratSelesai;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('klasifikasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode')->unique();
            $table->string('nama');
            $table->boolean('wajib_item_pkpt')->default(false);
            $table->string('syarat_selesai');
            $table->boolean('tampil_publik')->default(false);
            $table->timestamps();
        });

        // Fixed reference data (PRD §9.4): the five rows exist in every environment, so they are inserted here, not by a dev-only seeder.
        $now = now();
        $rows = [
            ['REGULER', 'REGULER', true, SyaratSelesai::LhpLengkap, true],
            ['KHUSUS', 'KHUSUS', false, SyaratSelesai::LhpLengkap, false],
            ['DIKLAT', 'DIKLAT', false, SyaratSelesai::Konfirmasi, false],
            ['LUAR_DAERAH', 'LUAR DAERAH', false, SyaratSelesai::LaporanOpsional, false],
            ['LAIN_LAIN', 'LAIN-LAIN', false, SyaratSelesai::DokumenOpsional, false],
        ];
        foreach ($rows as [$kode, $nama, $wajib, $syarat, $publik]) {
            DB::table('klasifikasi')->insert([
                'kode' => $kode, 'nama' => $nama, 'wajib_item_pkpt' => $wajib, 'syarat_selesai' => $syarat->value,
                'tampil_publik' => $publik, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('klasifikasi');
    }
};
