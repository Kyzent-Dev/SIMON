<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spt', function (Blueprint $table) {
            $table->timestamp('ditandai_selesai_pada')->nullable();
            $table->foreignId('ditandai_selesai_oleh')->nullable()->constrained('users')->restrictOnDelete();
        });

        Schema::create('spt_kendala', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spt_id')->unique()->constrained('spt')->cascadeOnDelete(); // one kendala per SPT, edited in place
            $table->string('kategori', 100);
            $table->text('uraian');
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('dibuat_oleh')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spt_kendala');
        Schema::table('spt', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ditandai_selesai_oleh');
            $table->dropColumn('ditandai_selesai_pada');
        });
    }
};
