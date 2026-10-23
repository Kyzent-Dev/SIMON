<?php

namespace App\Notifications;

use App\Models\PkptPerubahan;

class PengingatSkPerubahan extends NotifikasiSimon
{
    protected int $perubahanId;

    protected int $pkptId;

    protected int $tahun;

    protected ?int $nomor;

    public function __construct(PkptPerubahan $p)
    {
        $this->perubahanId = $p->id;
        $this->pkptId = $p->pkpt_id;
        $this->tahun = (int) $p->pkpt->tahun;
        $this->nomor = $p->nomor_urut;
    }

    public function jenis(): string
    {
        return 'pengingat_sk_perubahan';
    }

    public function judul(): string
    {
        return 'SK Perubahan PKPT belum dilengkapi';
    }

    public function konteks(): ?string
    {
        return collect(["PKPT {$this->tahun}", $this->nomor ? "Perubahan ke-{$this->nomor}" : null])->filter()->implode(' · ');
    }

    public function url(): string
    {
        return route('admin.pkpt.perubahan', ['pkpt' => $this->pkptId, 'perubahan' => $this->perubahanId], false);
    }

    public function isi(): array
    {
        return ['Perubahan PKPT ini sudah disetujui, tetapi nomor, tanggal, atau berkas SK-nya belum lengkap.'];
    }
}
