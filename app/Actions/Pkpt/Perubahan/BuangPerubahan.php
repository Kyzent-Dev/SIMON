<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Enums\StatusUsulan;
use App\Models\PkptPerubahan;
use App\Models\PkptUsulan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Discards a draf/ditolak package that was never approved: its usulan reopen, the rows cascade, the SK file goes after commit. */
class BuangPerubahan
{
    use MengelolaPerubahan;

    public function handle(User $user, PkptPerubahan $p): void
    {
        Gate::forUser($user)->authorize('buang', $p);

        $berkas = DB::transaction(function () use ($p) {
            $p = $this->kunciTerbuka($p);
            if (! $p->bisaDibuang()) {
                throw ValidationException::withMessages(['perubahan' => self::BASI]);
            }
            PkptUsulan::where('perubahan_id', $p->id)->get()->each(fn (PkptUsulan $u) => $u->forceFill(['status' => StatusUsulan::Terbuka, 'perubahan_id' => null])->save());
            $p->operasi()->get()->each->delete(); // logged one by one; the FK cascade would skip the log
            $p->delete();

            return $p->berkas_sk;
        });

        if ($berkas) {
            Storage::disk('local')->delete($berkas);
        }
    }
}
