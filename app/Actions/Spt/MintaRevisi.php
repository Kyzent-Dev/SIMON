<?php

namespace App\Actions\Spt;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;

/** Bagian Umum asks the Irban to revise a pengajuan; the catatan (10–1000 characters) is stored as the riwayat alasan and shown to the Irban. */
class MintaRevisi
{
    use PutuskanPengajuan;

    public function handle(User $user, Spt $spt, string $catatan): Spt
    {
        $catatan = $this->catatan($user, $catatan);

        return $this->putuskan($user, $spt, fn () => [StatusPengajuan::PerluRevisi, KejadianSpt::PERLU_REVISI, $catatan]);
    }
}
