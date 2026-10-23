<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpt;
use App\Models\Spt;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/** Computed SPT flags (PRD §9.3); keys match `x-app.flag`. */
class PenandaSpt
{
    public function __construct(private KesesuaianPkpt $kesesuaian, private Settings $settings) {}

    /** @return list<string> */
    public function untuk(Spt $spt): array
    {
        return $this->hitung($spt, now('Asia/Jakarta')->startOfDay(), $this->settings->get('penanda.batas_terlambat_hari'));
    }

    /** The flag rules; today and the late threshold are read once by the caller (untukBanyak reads them once per batch). */
    private function hitung(Spt $spt, CarbonInterface $hariIni, int $batas): array
    {
        $diterima = $spt->status_pengajuan === StatusPengajuan::Diterima;
        $tanggal = $hariIni->toDateString();
        $penanda = [];

        if ($spt->pkpt_item_id === null && $spt->status_pengajuan !== StatusPengajuan::Draf) {
            $penanda[] = 'non_pkpt';
        }
        if ($this->tidakSesuaiPkpt($spt)) {
            $penanda[] = 'tidak_sesuai_pkpt';
        }
        if ($spt->status($tanggal) === StatusSpt::PenyusunanLaporan && $spt->tanggal_selesai->startOfDay()->diffInDays($hariIni) > $batas) {
            $penanda[] = 'terlambat';
        }
        if ($diterima && $spt->tanggal_mulai !== null && $tanggal > $spt->tanggal_mulai->toDateString()
            && ! $this->adaFinal($spt)) {
            $penanda[] = 'spt_final_belum_diupload';
        }

        return $penanda;
    }

    /**
     * Flags for many SPT with a constant number of queries: eager-loads what untuk() reads, then runs the same rules.
     *
     * @param  Collection<int, Spt>  $spts
     * @return array<int, list<string>> keyed by SPT id
     */
    public function untukBanyak(Collection $spts): array
    {
        $spts = new EloquentCollection($spts->all());
        $spts->loadMissing('pkptItem');
        $spts->loadExists(['dokumen as final_ada' => fn ($q) => $q->where('jenis', 'final')]);
        [$hariIni, $batas] = [now('Asia/Jakarta')->startOfDay(), $this->settings->get('penanda.batas_terlambat_hari')];

        return $spts->mapWithKeys(fn (Spt $spt) => [$spt->id => $this->hitung($spt, $hariIni, $batas)])->all();
    }

    public function perluKendala(Spt $spt): bool
    {
        return $this->tidakSesuaiPkpt($spt) && ! $spt->kendala()->exists();
    }

    private function adaFinal(Spt $spt): bool
    {
        return array_key_exists('final_ada', $spt->getAttributes())
            ? (bool) $spt->final_ada
            : $spt->dokumen()->where('jenis', 'final')->exists();
    }

    private function tidakSesuaiPkpt(Spt $spt): bool
    {
        return $spt->pkptItem !== null && $spt->status_pengajuan === StatusPengajuan::Diterima
            && $spt->tanggal_mulai !== null && $spt->tanggal_selesai !== null
            && ! $this->kesesuaian->sesuai($spt->pkptItem, $spt->tanggal_mulai, $spt->tanggal_selesai);
    }
}
