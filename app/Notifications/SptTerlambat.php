<?php

namespace App\Notifications;

use App\Models\Spt;

class SptTerlambat extends NotifikasiSintas
{
    private string $nomor;

    private int $sptId;

    private string $selesai;

    public function __construct(Spt $spt, private int $batasHari)
    {
        $this->nomor = $spt->nomor_lengkap;
        $this->sptId = $spt->id;
        $this->selesai = $spt->tanggal_selesai->translatedFormat('d F Y');
    }

    public function jenis(): string
    {
        return 'spt_terlambat';
    }

    public function judul(): string
    {
        return 'SPT terlambat diselesaikan';
    }

    public function konteks(): ?string
    {
        return "SPT {$this->nomor}";
    }

    public function url(): string
    {
        return route('spt.show', $this->sptId, absolute: false);
    }

    public function isi(): array
    {
        return ["Penyusunan laporan sudah lebih dari {$this->batasHari} hari sejak tanggal selesai {$this->selesai}."];
    }
}
