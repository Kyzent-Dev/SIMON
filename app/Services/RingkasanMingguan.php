<?php

namespace App\Services;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPerubahan;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Models\SptRiwayat;
use Carbon\CarbonImmutable;

/** Weekly summary figures (PRD §7.10): counts over the 7 days before `$senin`, plus a snapshot of what is pending now. */
class RingkasanMingguan
{
    private const MAKS_DAFTAR = 10;

    public function __construct(private PenandaSpt $penanda, private Settings $settings) {}

    /** @return array{awal: CarbonImmutable, akhir: CarbonImmutable, nomor: int, selesai: int, menunggu_nomor: int, perubahan: int, terlambat: int, daftar: list<string>, lainnya: int} */
    public function hitung(CarbonImmutable $senin): array
    {
        $senin = $senin->startOfDay();
        $awal = $senin->subDays(7);
        $riwayat = fn (array $kejadian) => SptRiwayat::whereIn('kejadian', $kejadian)->where('created_at', '>=', $awal)->where('created_at', '<', $senin)->count();

        $hariIni = now('Asia/Jakarta')->startOfDay()->toImmutable();
        $terlambat = Spt::with(['irban', 'pkptItem'])->where('status_pengajuan', StatusPengajuan::Diterima)->whereNull('ditandai_selesai_pada')
            ->whereDate('tanggal_selesai', '<', $hariIni->subDays($this->settings->get('penanda.batas_terlambat_hari')))
            ->orderBy('tanggal_selesai')->orderBy('id')->get()
            ->filter(fn (Spt $s) => in_array('terlambat', $this->penanda->untuk($s), true))->values();

        return [
            'awal' => $awal,
            'akhir' => $senin->subDay(),
            'nomor' => $riwayat([KejadianSpt::DITERIMA, KejadianSpt::DICATAT_MANUAL]),
            'selesai' => $riwayat([KejadianSpt::DITANDAI_SELESAI]),
            'menunggu_nomor' => Spt::where('status_pengajuan', StatusPengajuan::MenungguNomor)->count(),
            'perubahan' => PkptPerubahan::where('status', StatusPerubahan::Diajukan)->count(),
            'terlambat' => $terlambat->count(),
            'daftar' => $terlambat->take(self::MAKS_DAFTAR)->map(fn (Spt $s) => "{$s->nomor_lengkap} · {$s->irban->nama} · "
                .$s->tanggal_selesai->startOfDay()->diffInDays($hariIni).' hari sejak tanggal selesai')->all(),
            'lainnya' => max(0, $terlambat->count() - self::MAKS_DAFTAR),
        ];
    }
}
