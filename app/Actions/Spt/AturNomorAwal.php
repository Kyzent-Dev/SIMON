<?php

namespace App\Actions\Spt;

use App\Models\Irban;
use App\Models\NomorCounter;
use App\Models\Spt;
use App\Models\User;
use App\Services\Penomoran;
use App\Services\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Sets the last number from the physical stambook (PRD §7.7) on one register, before any number was issued on it. */
class AturNomorAwal
{
    public function handle(User $user, int $tahun, ?Irban $irban, int $nomor): NomorCounter
    {
        if ($nomor < 0) {
            throw ValidationException::withMessages(['nomor' => 'Nomor awal minimal 0.']);
        }
        if ($irban === null && app(Settings::class)->get('penomoran.scope') === 'per_irban') {
            throw ValidationException::withMessages(['irban_id' => 'Pilih Irban.']);
        }
        $kunci = app(Penomoran::class)->kunci($irban, $tahun);

        return DB::transaction(function () use ($user, $kunci, $nomor) {
            Penomoran::pastikanCounter($kunci);
            $counter = NomorCounter::where($kunci)->lockForUpdate()->firstOrFail();
            if (Spt::where('nomor_counter_id', $counter->id)->whereNotNull('nomor_urut')->exists()) {
                throw ValidationException::withMessages(['nomor' => 'Nomor awal hanya bisa diatur sebelum ada nomor yang terbit pada register ini.']);
            }
            $counter->forceFill(['nomor_terakhir' => $nomor, 'diatur_oleh' => $user->id, 'diatur_pada' => now()])->save();

            return $counter;
        });
    }
}
