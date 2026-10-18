<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('irban', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->string('kode_nomor')->unique();
            $table->string('email_notifikasi')->nullable();
            $table->json('email_tembusan')->nullable();
            $table->timestamps();
        });

        Schema::create('irban_tahunan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irban_id')->constrained('irban')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->foreignId('ppj_pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->timestamps();
            $table->unique(['irban_id', 'tahun']);
        });

        Schema::create('irban_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irban_tahunan_id')->constrained('irban_tahunan')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['irban_tahunan_id', 'pegawai_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irban_anggota');
        Schema::dropIfExists('irban_tahunan');
        Schema::dropIfExists('irban');
    }
};
