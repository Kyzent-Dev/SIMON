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
    /**
     * The composition rules as booleans, for the form's live strip and for handle(). ikut=false rows are ignored.
     *
     * @param  list<array{pegawai_id: int, peran: string, ikut?: bool}>  $rows
     * @return array{ketua: bool, supervisor: bool, duplikat: bool, jumlah: int} duplikat = true when there is no duplicate
     */
    public function cek(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn ($r) => ($r['ikut'] ?? true) !== false));
        $peran = array_count_values(array_map(fn ($r) => (string) ($r['peran'] ?? ''), $rows));
        $ids = array_column($rows, 'pegawai_id');

        return [
            'ketua' => ($peran[PeranTim::Ketua->value] ?? 0) === 1,
            'supervisor' => ($peran[PeranTim::SupervisorDalnis->value] ?? 0) <= 1,
            'duplikat' => count($ids) === count(array_unique($ids)),
            'jumlah' => count($rows),
        ];
    }

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
            $cek = $this->cek(array_map(fn ($h) => ['pegawai_id' => $h['pegawai_id'], 'peran' => $h['peran']->value], $hasil));
            if (! $rows) {
                $errors[] = 'Pilih minimal satu personel.';
            } elseif (! $cek['ketua']) {
                $errors[] = 'Tetapkan tepat satu Ketua Tim';
            }
            if (! $cek['supervisor']) {
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
