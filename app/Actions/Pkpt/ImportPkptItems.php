<?php

namespace App\Actions\Pkpt;

use App\Models\Pkpt;
use App\Models\PkptItem;
use Illuminate\Support\Facades\DB;

class ImportPkptItems
{
    /** Appends analyzed rows (not error; warnings are duplicates, kept only when !$skipDuplicates) in one transaction; returns the count. */
    public function handle(Pkpt $pkpt, array $rows, bool $skipDuplicates = true): int
    {
        $rows = array_filter($rows, fn ($r) => $r['status'] === 'valid' || ($r['status'] === 'warning' && ! $skipDuplicates));

        return DB::transaction(function () use ($pkpt, $rows) {
            SavePkpt::ensureDraf($pkpt->fresh());
            foreach ($rows as $r) {
                PkptItem::create(['pkpt_id' => $pkpt->id] + SavePkptItem::validated($pkpt, $r['data']));
            }

            return count($rows);
        });
    }
}
