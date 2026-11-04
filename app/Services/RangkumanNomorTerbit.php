<?php

namespace App\Services;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use Carbon\CarbonImmutable;

/** Numbers issued the day before `$hariIni` (Asia/Jakarta): counts overall and per irban, for the daily Pimpinan summary. */
class RangkumanNomorTerbit
{
    /** @return array{tanggal: string, jumlah: int, per_irban: array<string, int>} */
    public function hitung(CarbonImmutable $hariIni): array
    {
        $hariIni = $hariIni->startOfDay();
        $kemarin = $hariIni->subDay();

        $perIrban = Spt::with('irban:id,nama')->where('status_pengajuan', StatusPengajuan::Diterima)
            ->whereHas('riwayat', fn ($q) => $q->whereIn('kejadian', [KejadianSpt::DITERIMA, KejadianSpt::DICATAT_MANUAL])
                ->where('created_at', '>=', $kemarin)->where('created_at', '<', $hariIni))
            ->get()->countBy(fn (Spt $s) => $s->irban->nama)->sortKeys()->all();

        return ['tanggal' => $kemarin->toDateString(), 'jumlah' => array_sum($perIrban), 'per_irban' => $perIrban];
    }
}
