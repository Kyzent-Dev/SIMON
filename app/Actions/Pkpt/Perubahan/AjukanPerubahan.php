<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Enums\StatusPerubahan;
use App\Models\PkptPerubahan;
use App\Models\User;
use App\Notifications\PerubahanPkptDiajukan;
use App\Services\PenerimaNotifikasi;
use App\Services\PerubahanPkpt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/** Admin submits a valid draf/ditolak package to Pimpinan. The komentar of an earlier rejection stays (history). */
class AjukanPerubahan
{
    use MengelolaPerubahan;

    public function __construct(private PerubahanPkpt $perubahan, private PenerimaNotifikasi $penerima) {}

    public function handle(User $user, PkptPerubahan $p): PkptPerubahan
    {
        Gate::forUser($user)->authorize('ajukan', $p);

        $p = DB::transaction(function () use ($user, $p) {
            $p = $this->kunciTerbuka($p);
            if ($alasan = $this->perubahan->cek($p)) {
                throw ValidationException::withMessages(['perubahan' => $alasan]);
            }
            $p->forceFill(['status' => StatusPerubahan::Diajukan, 'diajukan_pada' => now()])->save();
            $this->catat($p, 'diajukan', $user);

            return $p;
        });

        Notification::send($this->penerima->pimpinan(), new PerubahanPkptDiajukan($p));

        return $p;
    }
}
