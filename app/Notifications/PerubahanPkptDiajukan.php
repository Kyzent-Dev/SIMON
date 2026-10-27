<?php

namespace App\Notifications;

use App\Models\PkptPerubahan;

class PerubahanPkptDiajukan extends NotifikasiSintas
{
    protected int $perubahanId;

    protected int $tahun;

    protected int $jumlah;

    public function __construct(PkptPerubahan $p)
    {
        $this->perubahanId = $p->id;
        $this->tahun = (int) $p->pkpt->tahun;
        $this->jumlah = $p->operasi()->count();
    }

    public function jenis(): string
    {
        return 'perubahan_pkpt_diajukan';
    }

    public function judul(): string
    {
        return 'Perubahan PKPT menunggu persetujuan';
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
        return ["Admin mengajukan perubahan PKPT {$this->tahun} dengan {$this->jumlah} operasi. Tinjau lalu setujui atau tolak."];
    }
}
