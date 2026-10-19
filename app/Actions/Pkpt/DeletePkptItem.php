<?php

namespace App\Actions\Pkpt;

use App\Models\PkptItem;
use App\Models\Spt;
use Illuminate\Validation\ValidationException;

class DeletePkptItem
{
    public function handle(PkptItem $item): void
    {
        SavePkpt::ensureDraf($item->pkpt);
        if (Spt::where('pkpt_item_id', $item->id)->exists()) {
            throw ValidationException::withMessages(['pkpt' => 'Item PKPT ini sudah dipakai SPT dan tidak dapat dihapus.']);
        }
        $item->delete();
    }
}
