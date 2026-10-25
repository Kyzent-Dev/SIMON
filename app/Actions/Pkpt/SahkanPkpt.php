<?php

namespace App\Actions\Pkpt;

use App\Enums\StatusPkpt;
use App\Models\Pkpt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SahkanPkpt
{
    /** Irreversible: there is no way back to Draf. */
    public function handle(Pkpt $pkpt, User $user): Pkpt
    {
        SavePkpt::ensureDraf($pkpt);
        if (blank($pkpt->nomor_sk) || ! $pkpt->tanggal_sk) {
            throw ValidationException::withMessages(['nomor_sk' => 'Isi nomor dan tanggal SK Bupati sebelum mengesahkan.']);
        }
        if (! $pkpt->items()->exists()) {
            throw ValidationException::withMessages(['pkpt' => 'PKPT belum memiliki item.']);
        }
        DB::transaction(function () use ($pkpt, $user) {
            $pkpt->forceFill(['status' => StatusPkpt::Disahkan, 'disahkan_pada' => now(), 'disahkan_oleh' => $user->id])->save();
            // Snapshot for "jadwal semula": a later Perubahan PKPT moves the item dates, never these.
            // Per model (not one UPDATE) so each item's snapshot is in the activity log.
            $pkpt->items()->get()->each(fn ($item) => $item->forceFill([
                'tanggal_mulai_disahkan' => $item->tanggal_mulai, 'tanggal_selesai_disahkan' => $item->tanggal_selesai,
            ])->save());
        });

        return $pkpt;
    }
}
