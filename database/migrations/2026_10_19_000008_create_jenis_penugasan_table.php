<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_penugasan', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->char('warna', 7);
            $table->char('warna_teks', 7);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_penugasan');
    }
};
