<?php

namespace App\Actions\Spt;

use App\Enums\PeranTim;
use App\Models\Pegawai;
use App\Models\Tim;
use Illuminate\Validation\ValidationException;

class ValidatePersonel
{
    /**
     * @param  list<array{pegawai_id: int, peran: string, sumber?: string, tim_id?: ?int, ikut?: bool}>  $rows  display order; ikut=false rows are dropped
     * @param  list<int>  $sudahAda  pegawai already on the SPT (may stay even if now inactive)
     * @param  bool  $lengkap  true for ajukan: also min one, exactly one Ketua, max one Supervisor/Dalnis
     * @return list<array{pegawai_id: int, peran: PeranTim, urutan: int, sumber: string, tim_id: ?int}>
     */
    public function handle(array $rows, int $irbanId, array $sudahAda = [], bool $lengkap = true): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => ($r['ikut'] ?? true) !== false));
        $pegawai = Pegawai::whereIn('id', array_filter(array_column($rows, 'pegawai_id')))->get()->keyBy('id');
        $timIds = Tim::where('irban_id', $irbanId)->pluck('id')->all();
        $errors = [];
        $hasil = [];
        $dilihat = [];
        foreach ($rows as $i => $r) {
            $g = $pegawai[$r['pegawai_id'] ?? 0] ?? null;
            $peran = PeranTim::tryFrom((string) ($r['peran'] ?? ''));
            if (! $g || ! $peran) {
                $errors[] = 'Data personel tidak valid.';

                continue;
            }
            if (isset($dilihat[$g->id])) {
                $errors[] = "{$g->nama_bergelar} tercantum lebih dari sekali.";

                continue;
            }
            $dilihat[$g->id] = true;
            if (! $g->aktif && ! in_array($g->id, $sudahAda, true)) {
                $errors[] = "{$g->nama_bergelar} tidak aktif dan tidak dapat ditambahkan.";
            }
            $tim = ($r['sumber'] ?? 'tambahan') === 'tim' && in_array($r['tim_id'] ?? null, $timIds, true);
            $hasil[] = ['pegawai_id' => $g->id, 'peran' => $peran, 'urutan' => $i + 1, 'sumber' => $tim ? 'tim' : 'tambahan', 'tim_id' => $tim ? (int) $r['tim_id'] : null];
        }
        if ($lengkap) {
            $hitung = fn (PeranTim $p) => count(array_filter($hasil, fn ($h) => $h['peran'] === $p));
            if (! $rows) {
                $errors[] = 'Pilih minimal satu personel.';
            } elseif ($hitung(PeranTim::Ketua) !== 1) {
                $errors[] = 'Tetapkan tepat satu Ketua Tim';
            }
            if ($hitung(PeranTim::SupervisorDalnis) > 1) {
                $errors[] = 'Maksimal satu Supervisor/Dalnis.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['personel' => array_values(array_unique($errors))]);
        }

        // urutan 1..n over the kept rows (display order), not the unchecked ones.
        foreach ($hasil as $n => &$h) {
            $h['urutan'] = $n + 1;
        }

        return $hasil;
    }
}
