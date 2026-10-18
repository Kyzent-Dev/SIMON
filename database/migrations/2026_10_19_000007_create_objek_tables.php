<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('objek', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jenis');
            $table->foreignId('parent_id')->nullable()->constrained('objek')->restrictOnDelete();
            $table->timestamps();
            $table->index('jenis');
        });

        Schema::create('irban_wilayah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irban_id')->constrained('irban')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->foreignId('objek_id')->constrained('objek')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['tahun', 'objek_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('irban_wilayah');
        Schema::dropIfExists('objek');
    }
};
