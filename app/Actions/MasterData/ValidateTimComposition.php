<?php

namespace App\Actions\MasterData;

use App\Enums\PeranTim;
use Illuminate\Validation\ValidationException;

/** Team rules (PRD): exactly one Supervisor/Dalnis, exactly one Ketua, any number of Anggota, each pegawai at most once. */
class ValidateTimComposition
{
    /** @param list<array{pegawai_id: int|string, peran_default: string}> $members */
    public function handle(array $members): void
    {
        $errors = [];
        $perans = array_map(fn ($m) => PeranTim::tryFrom((string) ($m['peran_default'] ?? '')), $members);
        if (in_array(null, $perans, true)) {
            $errors[] = 'Pilih peran yang valid.';
        }
        $ids = array_column($members, 'pegawai_id');
        if (count($ids) !== count(array_unique($ids))) {
            $errors[] = 'Pegawai tidak boleh dipilih dua kali dalam satu tim.';
        }
        $count = array_count_values(array_map(fn ($p) => $p?->value ?? '', $perans));
        if (($count[PeranTim::SupervisorDalnis->value] ?? 0) !== 1) {
            $errors[] = 'Setiap tim wajib punya tepat satu Supervisor/Dalnis.';
        }
        if (($count[PeranTim::Ketua->value] ?? 0) !== 1) {
            $errors[] = 'Setiap tim wajib punya tepat satu Ketua.';
        }
        if ($errors) {
            throw ValidationException::withMessages(['members' => $errors]);
        }
    }
}
