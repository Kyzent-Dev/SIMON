<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Enums\StatusPerubahan;
use App\Models\PkptPerubahan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Turns the daily SK reminder of an approved package off or back on. */
class AturPengingatSk
{
    use MengelolaPerubahan;

    public function handle(User $user, PkptPerubahan $p, bool $nyala): PkptPerubahan
    {
        Gate::forUser($user)->authorize('kelolaSk', $p);

        return DB::transaction(function () use ($user, $p, $nyala) {
            $p = PkptPerubahan::whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->status !== StatusPerubahan::Disetujui) {
                throw ValidationException::withMessages(['perubahan' => 'Pengingat SK hanya untuk perubahan yang sudah disetujui.']);
            }
            if ($nyala === ($p->pengingat_sk_dimatikan_pada === null)) {
                return $p;
            }
            $p->forceFill($nyala
                ? ['pengingat_sk_dimatikan_oleh' => null, 'pengingat_sk_dimatikan_pada' => null]
                : ['pengingat_sk_dimatikan_oleh' => $user->id, 'pengingat_sk_dimatikan_pada' => now()])->save();
            $this->catat($p, $nyala ? 'pengingat_dinyalakan' : 'pengingat_dimatikan', $user);

            return $p;
        });
    }
}
