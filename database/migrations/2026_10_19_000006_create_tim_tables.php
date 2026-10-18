<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tim', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irban_id')->constrained('irban')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->string('nama');
            $table->timestamps();
            $table->unique(['irban_id', 'tahun', 'nama']);
        });

        Schema::create('tim_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tim_id')->constrained('tim')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('peran_default');
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();
            $table->unique(['tim_id', 'pegawai_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tim_anggota');
        Schema::dropIfExists('tim');
    }
};
