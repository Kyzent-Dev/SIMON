<?php

use App\Models\Aktivitas;
use App\Models\Spt;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // No FK: the log outlives the SPT it describes. Filled by Aktivitas::creating; old rows below.
            $table->unsignedBigInteger('spt_id')->nullable()->index();
        });

        // Backfill with the query builder: the Aktivitas model refuses updates. Portable PHP, no JSON SQL.
        $spt = (new Spt)->getMorphClass();
        $anak = [];
        foreach (Aktivitas::ANAK_SPT as $kelas) {
            $anak[(new $kelas)->getMorphClass()] = (new $kelas)->getTable();
        }

        DB::table('activity_log')->whereIn('subject_type', [$spt, ...array_keys($anak)])->orderBy('id')
            ->chunkById(500, function ($baris) use ($spt, $anak) {
                $perTabel = [];
                $sptId = [];
                foreach ($baris as $b) {
                    if ($b->subject_type === $spt) {
                        $sptId[$b->id] = (int) $b->subject_id;

                        continue;
                    }
                    $p = json_decode((string) $b->properties, true) ?: [];
                    $id = $p['old']['spt_id'] ?? $p['attributes']['spt_id'] ?? null;
                    if ($id !== null) {
                        $sptId[$b->id] = (int) $id;
                    } else {
                        $perTabel[$anak[$b->subject_type]][] = $b->subject_id; // live child: ask its table
                    }
                }
                $induk = [];
                foreach ($perTabel as $tabel => $ids) {
                    $induk[$tabel] = DB::table($tabel)->whereIn('id', array_unique($ids))->pluck('spt_id', 'id');
                }
                foreach ($baris as $b) {
                    $id = $sptId[$b->id] ?? ($induk[$anak[$b->subject_type] ?? ''][$b->subject_id] ?? null);
                    if ($id !== null) {
                        DB::table('activity_log')->where('id', $b->id)->update(['spt_id' => $id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['spt_id']);
            $table->dropColumn('spt_id');
        });
    }
};
