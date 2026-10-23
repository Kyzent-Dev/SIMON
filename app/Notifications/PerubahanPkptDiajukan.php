<?php

namespace App\Notifications;

use App\Models\PkptPerubahan;

class PerubahanPkptDiajukan extends NotifikasiSimon
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
        // shortcut: literal path of route pimpinan.perubahan-pkpt.show (E7), which Task 8 registers; switch to route() then.
        return "/pimpinan/perubahan-pkpt/{$this->perubahanId}";
    }

    public function isi(): array
    {
        return ["Admin mengajukan perubahan PKPT {$this->tahun} dengan {$this->jumlah} operasi. Tinjau lalu setujui atau tolak."];
    }
}
