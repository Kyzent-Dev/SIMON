<?php

namespace App\Actions\MasterData;

use App\Models\Pegawai;
use Illuminate\Validation\ValidationException;

class DeletePegawai
{
    public function handle(Pegawai $pegawai): void
    {
        if ($pegawai->isReferenced()) {
            throw ValidationException::withMessages(['delete' => 'Pegawai ini sudah dipakai di data lain dan tidak dapat dihapus. Nonaktifkan saja.']);
        }
        $pegawai->delete();
    }
}
