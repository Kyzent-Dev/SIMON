<?php

namespace App\Actions\MasterData;

use App\Models\Irban;
use Illuminate\Validation\ValidationException;

class DeleteIrban
{
    public function handle(Irban $irban): void
    {
        if ($irban->isReferenced()) {
            throw ValidationException::withMessages(['delete' => 'Irban ini sudah dipakai di data lain dan tidak dapat dihapus.']);
        }
        $irban->delete();
    }
}
