<?php

namespace App\Actions\Pkpt;

use App\Models\Aktivitas;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ImportPkptItems
{
    /** Appends analyzed rows (not error; warnings are duplicates, kept only when !$skipDuplicates) in one transaction; returns the count. */
    public function handle(User $admin, Pkpt $pkpt, array $rows, bool $skipDuplicates = true, string $berkas = ''): int
    {
        $rows = array_filter($rows, fn ($r) => $r['status'] === 'valid' || ($r['status'] === 'warning' && ! $skipDuplicates));

        $n = DB::transaction(function () use ($pkpt, $rows) {
            SavePkpt::ensureDraf($pkpt->fresh());
            foreach ($rows as $r) {
                PkptItem::create(['pkpt_id' => $pkpt->id] + SavePkptItem::validated($pkpt, $r['data']));
            }

            return count($rows);
        });
        if ($n > 0) {
            Aktivitas::catatImpor($admin, 'pkpt', $berkas, $n);
        }

        return $n;
    }
}
