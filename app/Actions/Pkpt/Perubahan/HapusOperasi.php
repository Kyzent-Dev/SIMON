<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Enums\StatusUsulan;
use App\Models\PkptPerubahanItem;
use App\Models\PkptUsulan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Removes an operation from a draf/ditolak package; the usulan it came from goes back to `terbuka`. */
class HapusOperasi
{
    use MengelolaPerubahan;

    public function handle(User $user, PkptPerubahanItem $op): void
    {
        Gate::forUser($user)->authorize('update', $op->perubahan);

        DB::transaction(function () use ($op) {
            $this->kunciTerbuka($op->perubahan);
            $op = PkptPerubahanItem::whereKey($op->id)->first();
            if (! $op) {
                return;
            }
            if ($op->usulan_id) {
                PkptUsulan::whereKey($op->usulan_id)->first()?->forceFill(['status' => StatusUsulan::Terbuka, 'perubahan_id' => null])->save();
            }
            $op->delete();
        });
    }
}
