<?php

namespace App\Actions\Pkpt;

use App\Enums\StatusPkpt;
use App\Models\Pkpt;
use App\Models\User;
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
        $pkpt->forceFill(['status' => StatusPkpt::Disahkan, 'disahkan_pada' => now(), 'disahkan_oleh' => $user->id])->save();

        return $pkpt;
    }
}
