<?php

namespace App\Notifications;

use App\Models\Spt;

class PengingatSptFinal extends NotifikasiSintas
{
    private string $nomor;

    private int $sptId;

    private string $mulai;

    public function __construct(Spt $spt)
    {
        $this->nomor = $spt->nomor_lengkap;
        $this->sptId = $spt->id;
        $this->mulai = $spt->tanggal_mulai->translatedFormat('d F Y');
    }

    public function jenis(): string
    {
        return 'pengingat_spt_final';
    }

    public function judul(): string
    {
        return 'Pengingat: SPT final belum diunggah';
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
        return ["Penugasan dimulai {$this->mulai}. Unggah SPT final bertanda tangan di halaman detail SPT."];
    }
}
