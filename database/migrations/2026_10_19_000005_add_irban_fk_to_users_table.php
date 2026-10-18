<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Orphans would violate the new constraint.
        DB::table('users')->whereNotNull('irban_id')->whereNotIn('irban_id', DB::table('irban')->select('id'))->update(['irban_id' => null]);

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('irban_id')->references('id')->on('irban')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['irban_id']);
        });
    }
};
