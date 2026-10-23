<?php

namespace App\Notifications;

use App\Models\PkptPerubahan;

class PengingatPerubahanPkpt extends NotifikasiSimon
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
        // shortcut: literal path of route pimpinan.perubahan-pkpt.show (E7), which Task 8 registers; switch to route() then.
        return "/pimpinan/perubahan-pkpt/{$this->perubahanId}";
    }

    public function isi(): array
    {
        return ["Perubahan PKPT {$this->tahun} diajukan sejak {$this->diajukan} dan belum diputuskan."];
    }
}
