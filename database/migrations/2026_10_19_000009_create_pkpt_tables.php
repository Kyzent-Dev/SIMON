<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpt', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->string('nomor_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('status')->default('draf');
            $table->timestamp('disahkan_pada')->nullable();
            $table->foreignId('disahkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('pkpt_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpt_id')->constrained('pkpt')->cascadeOnDelete();
            $table->foreignId('irban_id')->constrained('irban')->restrictOnDelete();
            $table->foreignId('jenis_penugasan_id')->constrained('jenis_penugasan')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('keterangan', 255)->nullable();
            $table->unsignedSmallInteger('target_spt')->nullable();
            $table->timestamps();
            $table->index(['pkpt_id', 'irban_id']);
        });

        Schema::create('pkpt_item_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpt_item_id')->constrained('pkpt_item')->cascadeOnDelete();
            $table->date('tanggal_mulai_semula');
            $table->date('tanggal_selesai_semula');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('status')->default('menunggu');
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('komentar')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpt_item_revisi');
        Schema::dropIfExists('pkpt_item');
        Schema::dropIfExists('pkpt');
    }
};
