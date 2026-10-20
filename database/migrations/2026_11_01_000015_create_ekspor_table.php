<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Queued exports (more than 1000 rows): built by App\Jobs\BuatEkspor, downloaded by the owner, deleted after 7 days. */
    public function up(): void
    {
        Schema::create('ekspor', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->string('format', 4);
            $table->json('filter');
            $table->string('nama_file');
            $table->string('path')->nullable();
            $table->string('status', 10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekspor');
    }
};
