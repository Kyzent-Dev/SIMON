<?php

namespace App\Actions\MasterData;

use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveIrbanTahunan
{
    /** @param list<int> $anggotaIds */
    public function handle(Irban $irban, int $tahun, ?int $ppjId, array $anggotaIds): IrbanTahunan
    {
        $y = (int) now('Asia/Jakarta')->year;
        if ($tahun < $y - 2 || $tahun > $y + 1) {
            throw ValidationException::withMessages(['tahun' => 'Tahun tidak valid.']);
        }
        foreach ($anggotaIds as $id) {
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw ValidationException::withMessages(['anggota' => 'Pilih pegawai aktif.']);
            }
        }
        $anggotaIds = array_values(array_unique(array_map('intval', $anggotaIds)));

        // The one-irban-per-year rule is app-enforced, so serialise saves per year (check and write together).
        try {
            return Cache::lock("irban-tahunan-{$tahun}", 10)->block(5, fn () => DB::transaction(fn () => $this->save($irban, $tahun, $ppjId, $anggotaIds)));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['anggota' => 'Data sedang diperbarui oleh pengguna lain. Coba lagi.']);
        }
    }

    private function save(Irban $irban, int $tahun, ?int $ppjId, array $anggotaIds): IrbanTahunan
    {
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

        $row = IrbanTahunan::updateOrCreate(['irban_id' => $irban->id, 'tahun' => $tahun], ['ppj_pegawai_id' => $ppjId]);
        $lama = $row->anggota()->pluck('pegawai.nama_bergelar')->all();
        $row->anggota()->sync($anggotaIds);
        $baru = $row->anggota()->pluck('pegawai.nama_bergelar')->all();
        sort($lama, SORT_NATURAL | SORT_FLAG_CASE);
        sort($baru, SORT_NATURAL | SORT_FLAG_CASE);
        // irban_anggota is a pivot (no model event): explicit diff row, only when the composition changed.
        if ($lama !== $baru) {
            activity()->performedOn($row)->event('updated')->withProperties(['old' => ['anggota' => $lama], 'attributes' => ['anggota' => $baru]])->log('updated');
        }

        return $row;
    }
}
