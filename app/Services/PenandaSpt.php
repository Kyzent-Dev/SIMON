<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpt;
use App\Models\Spt;

/** Computed SPT flags (PRD §9.3); keys match `x-app.flag`. */
class PenandaSpt
{
    public function __construct(private KesesuaianPkpt $kesesuaian, private Settings $settings) {}

    /** @return list<string> */
    public function untuk(Spt $spt): array
    {
        $diterima = $spt->status_pengajuan === StatusPengajuan::Diterima;
        $hariIni = now('Asia/Jakarta')->startOfDay();
        $penanda = [];

        if ($spt->pkpt_item_id === null && $spt->status_pengajuan !== StatusPengajuan::Draf) {
            $penanda[] = 'non_pkpt';
        }
        if ($this->tidakSesuaiPkpt($spt)) {
            $penanda[] = 'tidak_sesuai_pkpt';
        }
        if ($spt->status() === StatusSpt::PenyusunanLaporan
            && $spt->tanggal_selesai->startOfDay()->diffInDays($hariIni) > $this->settings->get('penanda.batas_terlambat_hari')) {
            $penanda[] = 'terlambat';
        }
        if ($diterima && $spt->tanggal_mulai !== null && $hariIni->toDateString() > $spt->tanggal_mulai->toDateString()
            && ! $spt->dokumen()->where('jenis', 'final')->exists()) {
            $penanda[] = 'spt_final_belum_diupload';
        }

        return $penanda;
    }

    public function perluKendala(Spt $spt): bool
    {
        return $this->tidakSesuaiPkpt($spt) && ! $spt->kendala()->exists();
    }

    private function tidakSesuaiPkpt(Spt $spt): bool
    {
        return $spt->pkptItem !== null && $spt->status_pengajuan === StatusPengajuan::Diterima
            && $spt->tanggal_mulai !== null && $spt->tanggal_selesai !== null
            && ! $this->kesesuaian->sesuai($spt->pkptItem, $spt->tanggal_mulai, $spt->tanggal_selesai);
    }
}
