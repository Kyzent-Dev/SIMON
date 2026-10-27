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

/**
 * Admin submits a valid draf/ditolak package to Pimpinan. The komentar of an earlier rejection stays (history).
 * $data (jenis, alasan) is the page's unsaved form: saved in the same transaction, so a refused submit leaves the package untouched.
 */
class AjukanPerubahan
{
    use MengelolaPerubahan;

    public function __construct(private PerubahanPkpt $perubahan, private PenerimaNotifikasi $penerima) {}

    public function handle(User $user, PkptPerubahan $p, ?array $data = null): PkptPerubahan
    {
        Gate::forUser($user)->authorize('ajukan', $p);
        $isi = $data === null ? [] : $this->isiPaket($data);

        $p = DB::transaction(function () use ($user, $p, $isi) {
            $p = $this->kunciTerbuka($p)->fill($isi);
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
