<?php

namespace App\Actions\MasterData;

use App\Models\JenisPenugasan;
use Illuminate\Validation\ValidationException;

class DeleteJenisPenugasan
{
    public function handle(JenisPenugasan $jenis): void
    {
        if ($jenis->isReferenced()) {
            throw ValidationException::withMessages(['delete' => 'Jenis penugasan ini sudah dipakai di data lain dan tidak dapat dihapus.']);
        }
        $jenis->delete();
    }
}
