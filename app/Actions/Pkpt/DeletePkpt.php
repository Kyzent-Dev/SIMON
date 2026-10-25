<?php

namespace App\Actions\Pkpt;

use App\Models\Pkpt;
use App\Models\Spt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeletePkpt
{
    /** Items go with it, one by one so each is in the activity log (the FK cascade would skip it). */
    public function handle(Pkpt $pkpt): void
    {
        SavePkpt::ensureDraf($pkpt);
        if (Spt::whereIn('pkpt_item_id', $pkpt->items()->select('id'))->exists()) {
            throw ValidationException::withMessages(['pkpt' => 'PKPT ini punya item yang sudah dipakai SPT dan tidak dapat dihapus.']);
        }
        DB::transaction(function () use ($pkpt) {
            $pkpt->items()->get()->each->delete();
            $pkpt->delete();
        });
    }
}
