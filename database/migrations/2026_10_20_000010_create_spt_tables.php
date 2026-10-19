<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomor_counter', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('tahun'); // 0 when penomoran.reset_tahunan = false
            $table->string('scope', 32); // global | irban:{irban_id}
            $table->unsignedInteger('nomor_terakhir')->default(0);
            $table->foreignId('diatur_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diatur_pada')->nullable();
            $table->timestamps();
            $table->unique(['tahun', 'scope']);
        });

        Schema::create('spt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('irban_id')->constrained('irban')->restrictOnDelete();
            $table->foreignId('klasifikasi_id')->constrained('klasifikasi')->restrictOnDelete();
            $table->foreignId('pkpt_item_id')->nullable()->constrained('pkpt_item')->restrictOnDelete();
            $table->foreignId('jenis_penugasan_id')->nullable()->constrained('jenis_penugasan')->restrictOnDelete();
            $table->foreignId('ppj_pegawai_id')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->string('periode_diperiksa', 255)->nullable();
            $table->text('uraian_untuk')->nullable();
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->text('catatan_bagian_umum')->nullable();
            $table->string('status_pengajuan')->default('draf');
            $table->unsignedInteger('nomor_urut')->nullable();
            $table->string('nomor_lengkap')->nullable()->unique();
            $table->foreignId('nomor_counter_id')->nullable()->constrained('nomor_counter')->restrictOnDelete();
            $table->unsignedSmallInteger('tahun')->nullable();
            $table->date('tanggal_spt')->nullable();
            $table->timestamp('diajukan_pada')->nullable();
            $table->string('sumber')->default('sistem');
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->string('nomor_lhp')->nullable();
            $table->date('tanggal_lhp')->nullable();
            $table->foreignId('penandatangan_pegawai_id')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->string('status_penandatangan')->nullable();
            $table->timestamps();
            $table->unique(['nomor_counter_id', 'nomor_urut']);
            $table->index(['irban_id', 'status_pengajuan']);
        });

        Schema::create('spt_objek', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spt_id')->constrained('spt')->cascadeOnDelete();
            $table->foreignId('objek_id')->constrained('objek')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['spt_id', 'objek_id']);
        });

        Schema::create('spt_personel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spt_id')->constrained('spt')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('peran');
            $table->unsignedSmallInteger('urutan');
            $table->string('sumber'); // tim | tambahan
            $table->foreignId('tim_id')->nullable()->constrained('tim')->nullOnDelete();
            $table->timestamps();
            $table->unique(['spt_id', 'pegawai_id']);
        });

        Schema::create('spt_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spt_id')->constrained('spt')->cascadeOnDelete();
            $table->string('jenis'); // draf | final | cover_lhp
            $table->string('path');
            $table->string('nama_asli');
            $table->unsignedInteger('ukuran');
            $table->char('sha256', 64);
            $table->foreignId('diunggah_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('spt_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spt_id')->constrained('spt')->cascadeOnDelete();
            $table->string('kejadian');
            $table->json('nilai_lama')->nullable();
            $table->json('nilai_baru')->nullable();
            $table->text('alasan')->nullable();
            $table->foreignId('oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spt_riwayat');
        Schema::dropIfExists('spt_dokumen');
        Schema::dropIfExists('spt_personel');
        Schema::dropIfExists('spt_objek');
        Schema::dropIfExists('spt');
        Schema::dropIfExists('nomor_counter');
    }
};
