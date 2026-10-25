<?php

namespace App\Actions\MasterData;

use App\Enums\JenisObjek;
use App\Models\Irban;
use App\Models\IrbanWilayah;
use App\Models\Objek;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveWilayahIrban
{
    /** Replaces the objek assigned to $irban for $tahun. @param list<int|string> $objekIds */
    public function handle(Irban $irban, int $tahun, array $objekIds): void
    {
        $y = (int) now('Asia/Jakarta')->year;
        if ($tahun < $y - 2 || $tahun > $y + 1) {
            throw ValidationException::withMessages(['objek' => 'Tahun di luar rentang yang diizinkan.']);
        }
        foreach ($objekIds as $id) {
            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw ValidationException::withMessages(['objek' => 'Pilih objek yang valid.']);
            }
        }
        $ids = array_values(array_unique(array_map('intval', $objekIds)));

        // One irban per objek per year is app-checked under a per-year lock; the DB unique is the backstop.
        try {
            Cache::lock("irban-wilayah-{$tahun}", 10)->block(5, fn () => DB::transaction(fn () => $this->save($irban, $tahun, $ids)));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['objek' => 'Data sedang diperbarui oleh pengguna lain. Coba lagi.']);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['objek' => 'Salah satu objek sudah ditugaskan ke Irban lain pada tahun ini.']);
        }
    }

    private function save(Irban $irban, int $tahun, array $ids): void
    {
        $allowed = Objek::whereIn('id', $ids)->whereIn('jenis', array_map(fn ($j) => $j->value, JenisObjek::wilayahTypes()))->pluck('id')->all();
        if (array_diff($ids, $allowed)) {
            throw ValidationException::withMessages(['objek' => 'Hanya kecamatan, dinas/badan, BUMD, dan RSUD yang dapat ditugaskan ke Irban.']);
        }
        $conflict = IrbanWilayah::with(['irban', 'objek'])->where('tahun', $tahun)->where('irban_id', '!=', $irban->id)->whereIn('objek_id', $ids)->first();
        if ($conflict) {
            throw ValidationException::withMessages(['objek' => "{$conflict->objek->nama} sudah ditugaskan ke {$conflict->irban->nama} pada tahun {$tahun}."]);
        }

        IrbanWilayah::where(['irban_id' => $irban->id, 'tahun' => $tahun])->whereNotIn('objek_id', $ids)->get()->each->delete();
        $have = IrbanWilayah::where(['irban_id' => $irban->id, 'tahun' => $tahun])->pluck('objek_id')->all();
        foreach (array_diff($ids, $have) as $id) {
            IrbanWilayah::create(['irban_id' => $irban->id, 'tahun' => $tahun, 'objek_id' => $id]);
        }
    }
}
