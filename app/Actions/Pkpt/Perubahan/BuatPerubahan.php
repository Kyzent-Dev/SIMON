<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Enums\StatusPkpt;
use App\Models\Pkpt;
use App\Models\PkptPerubahan;
use App\Models\User;
use App\Services\PerubahanPkpt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Admin opens a Perubahan PKPT on a Disahkan PKPT (one open package per PKPT); `perbarui` edits its jenis/alasan. */
class BuatPerubahan
{
    use MengelolaPerubahan;

    public const SUDAH_ADA = 'Masih ada perubahan yang belum selesai (Draf, Diajukan, atau Ditolak). Selesaikan atau buang dulu sebelum membuat yang baru.';

    public function __construct(private PerubahanPkpt $perubahan) {}

    public function handle(User $user, Pkpt $pkpt, array $data): PkptPerubahan
    {
        Gate::forUser($user)->authorize('create', PkptPerubahan::class);
        $isi = $this->isiPaket($data);

        return DB::transaction(function () use ($user, $pkpt, $isi) {
            $pkpt = Pkpt::whereKey($pkpt->id)->lockForUpdate()->firstOrFail();
            if ($pkpt->status !== StatusPkpt::Disahkan) {
                throw ValidationException::withMessages(['pkpt' => PerubahanPkpt::BELUM_DISAHKAN]);
            }
            if ($this->perubahan->terbuka($pkpt)) {
                throw ValidationException::withMessages(['perubahan' => self::SUDAH_ADA]);
            }
            $p = new PkptPerubahan(['pkpt_id' => $pkpt->id] + $isi);
            $p->forceFill(['dibuat_oleh' => $user->id])->save();
            $this->catat($p, 'dibuat', $user);

            return $p;
        });
    }

    public function perbarui(User $user, PkptPerubahan $p, array $data): PkptPerubahan
    {
        Gate::forUser($user)->authorize('update', $p);
        $isi = $this->isiPaket($data);

        return DB::transaction(function () use ($p, $isi) {
            $p = $this->kunciTerbuka($p);
            $p->fill($isi)->save();

            return $p;
        });
    }
}
