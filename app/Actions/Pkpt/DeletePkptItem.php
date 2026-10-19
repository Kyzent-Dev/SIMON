<?php

namespace App\Actions\Pkpt;

use App\Models\PkptItem;

class DeletePkptItem
{
    public function handle(PkptItem $item): void
    {
        SavePkpt::ensureDraf($item->pkpt);
        $item->delete();
    }
}
