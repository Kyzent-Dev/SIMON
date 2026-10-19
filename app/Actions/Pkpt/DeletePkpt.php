<?php

namespace App\Actions\Pkpt;

use App\Models\Pkpt;

class DeletePkpt
{
    /** Items go with it (FK cascade). */
    public function handle(Pkpt $pkpt): void
    {
        SavePkpt::ensureDraf($pkpt);
        $pkpt->delete();
    }
}
