<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Data only: `upload.maks_mb` is capped at 12 (Livewire's temporary upload limit), so clamp an already stored larger value. Idempotent. */
return new class extends Migration
{
    public function up(): void
    {
        $row = DB::table('settings')->where('key', 'upload.maks_mb')->first();
        if ($row && (int) json_decode($row->value) > 12) {
            DB::table('settings')->where('key', 'upload.maks_mb')->update(['value' => json_encode(12), 'updated_at' => now()]);
        }
    }

    public function down(): void {}
};
