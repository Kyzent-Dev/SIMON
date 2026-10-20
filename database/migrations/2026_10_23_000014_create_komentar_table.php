<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Comment threads on an SPT (PRD §8): one level, a root may be a Minta Penjelasan that pimpinan closes after the Irban replied. */
    public function up(): void
    {
        Schema::create('komentar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spt_id')->constrained('spt')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('komentar')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai');
            $table->text('isi');
            $table->boolean('minta_penjelasan')->default(false);
            $table->string('status_permintaan', 10)->nullable();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users');
            $table->timestamp('ditutup_pada')->nullable();
            $table->timestamps();

            $table->index(['spt_id', 'parent_id']);
            $table->index(['minta_penjelasan', 'status_permintaan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('komentar');
    }
};
