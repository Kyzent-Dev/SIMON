<?php

namespace App\Services;

use App\Enums\KejadianSpt;
use App\Models\SptRiwayat;
use Carbon\CarbonInterface;

/** Latest tracked SPT events for the Pimpinan dashboard panel (two queries: riwayat, then spt with irban eager loaded). */
class AktivitasSpt
{
    /** Events a Pimpinan follows: number lifecycle, final upload, kendala, completion. */
    public const KEJADIAN = [
        KejadianSpt::DITERIMA, KejadianSpt::DICATAT_MANUAL, KejadianSpt::DIBATALKAN,
        KejadianSpt::FINAL_DIUNGGAH, KejadianSpt::KENDALA_DIISI, KejadianSpt::DITANDAI_SELESAI,
    ];

    /** @return list<array{id: int, label: string, nomor: string, irban: string, waktu: CarbonInterface, url: string}> */
    public function terbaru(int $batas = 6): array
    {
        return SptRiwayat::with('spt:id,nomor_lengkap,irban_id', 'spt.irban:id,nama')
            ->whereIn('kejadian', self::KEJADIAN)
            ->orderByDesc('created_at')->orderByDesc('id')->limit($batas)->get()
            ->map(fn (SptRiwayat $r) => [
                'id' => $r->id,
                'label' => KejadianSpt::label($r->kejadian),
                'nomor' => $r->spt->nomor_lengkap ?? 'Nomor belum terbit',
                'irban' => $r->spt->irban->nama,
                'waktu' => $r->created_at->timezone('Asia/Jakarta'),
                'url' => route('spt.show', $r->spt_id),
            ])->all();
    }
}
