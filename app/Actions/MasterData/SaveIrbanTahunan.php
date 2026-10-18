<?php

namespace App\Actions\MasterData;

use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveIrbanTahunan
{
    /** @param list<int> $anggotaIds */
    public function handle(Irban $irban, int $tahun, ?int $ppjId, array $anggotaIds): IrbanTahunan
    {
        $anggotaIds = array_values(array_unique(array_map('intval', $anggotaIds)));
        $row = IrbanTahunan::where(['irban_id' => $irban->id, 'tahun' => $tahun])->first();

        // Pegawai must be active; people already in this composition may stay even if deactivated since.
        $allowed = Pegawai::where('aktif', true)->pluck('id')
            ->merge($row ? $row->anggota()->pluck('pegawai.id') : [])->merge($row?->ppj_pegawai_id ? [$row->ppj_pegawai_id] : []);
        if ($ppjId !== null && ! $allowed->contains($ppjId)) {
            throw ValidationException::withMessages(['ppj' => 'Pilih pegawai aktif.']);
        }
        if (array_diff($anggotaIds, $allowed->all())) {
            throw ValidationException::withMessages(['anggota' => 'Pilih pegawai aktif.']);
        }

        $conflict = IrbanTahunan::with('irban')->where('tahun', $tahun)->where('irban_id', '!=', $irban->id)
            ->whereHas('anggota', fn ($q) => $q->whereIn('pegawai.id', $anggotaIds))->first();
        if ($conflict) {
            throw ValidationException::withMessages(['anggota' => "Pegawai ini sudah menjadi anggota {$conflict->irban->nama} pada tahun {$tahun}."]);
        }

        return DB::transaction(function () use ($irban, $tahun, $ppjId, $anggotaIds) {
            $row = IrbanTahunan::updateOrCreate(['irban_id' => $irban->id, 'tahun' => $tahun], ['ppj_pegawai_id' => $ppjId]);
            $row->anggota()->sync($anggotaIds);

            return $row;
        });
    }
}
