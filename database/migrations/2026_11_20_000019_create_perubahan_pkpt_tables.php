<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pkpt_perubahan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpt_id')->constrained('pkpt')->cascadeOnDelete();
            $table->unsignedSmallInteger('nomor_urut')->nullable();
            $table->string('jenis');
            $table->text('alasan');
            $table->string('status')->default('draf');
            $table->string('sumber')->default('admin');
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diajukan_pada')->nullable();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('komentar_putusan')->nullable();
            $table->string('nomor_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('berkas_sk')->nullable();
            $table->char('hash_sk', 64)->nullable();
            $table->timestamp('sk_lengkap_pada')->nullable();
            $table->foreignId('pengingat_sk_dimatikan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pengingat_sk_dimatikan_pada')->nullable();
            $table->timestamp('pengingat_terakhir_pada')->nullable();
            $table->timestamps();
            $table->unique(['pkpt_id', 'nomor_urut']);
            $table->index(['pkpt_id', 'status']);
        });

        Schema::table('pkpt_item', function (Blueprint $table) {
            $table->string('status')->default('aktif');
            $table->date('tanggal_mulai_disahkan')->nullable();
            $table->date('tanggal_selesai_disahkan')->nullable();
            $table->foreignId('ditambahkan_perubahan_id')->nullable()->constrained('pkpt_perubahan')->nullOnDelete();
            $table->foreignId('dihapus_perubahan_id')->nullable()->constrained('pkpt_perubahan')->nullOnDelete();
            $table->index(['pkpt_id', 'status']);
        });

        Schema::create('pkpt_usulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpt_id')->constrained('pkpt')->cascadeOnDelete();
            $table->foreignId('irban_id')->constrained('irban')->restrictOnDelete();
            $table->foreignId('pkpt_item_id')->nullable()->constrained('pkpt_item')->restrictOnDelete();
            $table->string('aksi_usulan');
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->foreignId('jenis_penugasan_id')->nullable()->constrained('jenis_penugasan')->restrictOnDelete();
            $table->text('alasan');
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            // Required by the action; nullable only for rows migrated from the old schedule revisions (000020).
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->string('status')->default('terbuka');
            $table->foreignId('perubahan_id')->nullable()->constrained('pkpt_perubahan')->nullOnDelete();
            $table->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan_penutupan')->nullable();
            $table->timestamps();
            $table->index(['pkpt_id', 'status']);
            $table->index(['irban_id', 'status']);
        });

        Schema::create('pkpt_perubahan_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perubahan_id')->constrained('pkpt_perubahan')->cascadeOnDelete();
            $table->string('aksi');
            $table->foreignId('pkpt_item_id')->nullable()->constrained('pkpt_item')->restrictOnDelete();
            $table->json('data_baru');
            $table->json('data_lama')->nullable();
            $table->foreignId('usulan_id')->nullable()->constrained('pkpt_usulan')->nullOnDelete();
            $table->unsignedSmallInteger('urutan');
            $table->timestamps();
        });

        Schema::create('pkpt_perubahan_riwayat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perubahan_id')->constrained('pkpt_perubahan')->cascadeOnDelete();
            $table->string('kejadian');
            $table->foreignId('oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pkpt_perubahan_riwayat');
        Schema::dropIfExists('pkpt_perubahan_item');
        Schema::dropIfExists('pkpt_usulan');
        Schema::table('pkpt_item', function (Blueprint $table) {
            $table->dropForeign(['ditambahkan_perubahan_id']);
            $table->dropForeign(['dihapus_perubahan_id']);
            $table->dropIndex(['pkpt_id', 'status']);
            $table->dropColumn(['status', 'tanggal_mulai_disahkan', 'tanggal_selesai_disahkan', 'ditambahkan_perubahan_id', 'dihapus_perubahan_id']);
        });
        Schema::dropIfExists('pkpt_perubahan');
    }
};
