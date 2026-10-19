<?php

namespace App\Actions\Spt;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;
use App\Services\Penomoran;
use Carbon\CarbonImmutable;

/** Bagian Umum accepts a pengajuan: the decision date is today (Asia/Jakarta) and the number comes from the locked counter (PRD §9.1). */
class TerimaPengajuan
{
    use PutuskanPengajuan;

    public function __construct(private Penomoran $penomoran) {}

    public function handle(User $user, Spt $spt): Spt
    {
        return $this->putuskan($user, $spt, function (Spt $spt) {
            $tanggal = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
            $n = $this->penomoran->ambil($spt->irban, $tanggal, $spt->klasifikasi);
            $spt->forceFill([
                'nomor_urut' => $n['nomor_urut'], 'nomor_lengkap' => $n['nomor_lengkap'], 'nomor_counter_id' => $n['counter']->id,
                'tahun' => $tanggal->year, 'tanggal_spt' => $tanggal->toDateString(),
            ]);

            return [StatusPengajuan::Diterima, KejadianSpt::DITERIMA, null, ['nomor_lengkap' => $n['nomor_lengkap']]];
        });
    }
}
