<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Home hero slides managed by Admin (PRD §7.1). `gambar` is the base path `slide/{uuid}`; files are `{base}-1920.webp` and `{base}-960.webp` on the public disk. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landing_slides', function (Blueprint $table) {
            $table->id();
            $table->string('judul', 80);
            $table->string('kutipan', 200)->nullable();
            $table->string('pemilik_nama', 100)->nullable();
            $table->string('pemilik_jabatan', 100)->nullable();
            $table->string('gambar');
            $table->string('alt', 150)->nullable();
            $table->boolean('dekoratif')->default(false);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('urutan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_slides');
    }
};
