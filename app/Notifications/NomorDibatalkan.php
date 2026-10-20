<?php

namespace App\Notifications;

use App\Models\Spt;

class NomorDibatalkan extends NotifikasiSimon
{
    private string $nomor;

    private int $sptId;

    public function __construct(Spt $spt, private string $alasan)
    {
        $this->nomor = $spt->nomor_lengkap;
        $this->sptId = $spt->id;
    }

    public function jenis(): string
    {
        return 'nomor_dibatalkan';
    }

    public function judul(): string
    {
        return 'Nomor SPT dibatalkan';
    }

    public function konteks(): ?string
    {
        return "SPT {$this->nomor}";
    }

    public function url(): string
    {
        return route('spt.show', $this->sptId, absolute: false);
    }

    public function catatan(): ?string
    {
        return $this->alasan;
    }

    public function isi(): array
    {
        return ["Nomor SPT {$this->nomor} dibatalkan oleh Bagian Umum."];
    }
}
