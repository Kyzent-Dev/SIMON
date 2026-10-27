<?php

namespace App\Notifications;

use App\Models\PkptPerubahan;

class PengingatPerubahanPkpt extends NotifikasiSintas
{
    protected int $perubahanId;

    protected int $tahun;

    protected string $diajukan;

    public function __construct(PkptPerubahan $p)
    {
        $this->perubahanId = $p->id;
        $this->tahun = (int) $p->pkpt->tahun;
        $this->diajukan = $p->diajukan_pada->translatedFormat('d F Y');
    }

    public function jenis(): string
    {
        return 'pengingat_perubahan_pkpt';
    }

    public function judul(): string
    {
        return 'Perubahan PKPT masih menunggu persetujuan';
    }

    public function konteks(): ?string
    {
        return "PKPT {$this->tahun}";
    }

    public function url(): string
    {
        return route('pimpinan.perubahan-pkpt.show', ['perubahan' => $this->perubahanId], false);
    }

    public function isi(): array
    {
        return ["Perubahan PKPT {$this->tahun} diajukan sejak {$this->diajukan} dan belum diputuskan."];
    }
}
