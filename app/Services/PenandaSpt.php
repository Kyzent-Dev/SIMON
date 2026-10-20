<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpt;
use App\Models\Spt;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

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
        $spts->loadMissing('pkptItem.revisiDisetujui');
        $spts->loadExists(['dokumen as final_ada' => fn ($q) => $q->where('jenis', 'final')]);

        return $spts->mapWithKeys(fn (Spt $spt) => [$spt->id => $this->untuk($spt)])->all();
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
