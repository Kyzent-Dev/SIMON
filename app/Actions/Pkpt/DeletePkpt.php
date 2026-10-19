<?php

namespace App\Actions\Pkpt;

use App\Models\Pkpt;
use App\Models\Spt;
use Illuminate\Validation\ValidationException;

class DeletePkpt
{
    /** Items go with it (FK cascade). */
    public function handle(Pkpt $pkpt): void
    {
        SavePkpt::ensureDraf($pkpt);
        if (Spt::whereIn('pkpt_item_id', $pkpt->items()->select('id'))->exists()) {
            throw ValidationException::withMessages(['pkpt' => 'PKPT ini punya item yang sudah dipakai SPT dan tidak dapat dihapus.']);
        }
        $pkpt->delete();
    }
}
