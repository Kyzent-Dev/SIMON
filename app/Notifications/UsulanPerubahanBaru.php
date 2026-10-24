<?php

namespace App\Notifications;

use App\Models\PkptUsulan;

/** A new Irban usulan for every Admin; opens the PKPT's "Perubahan" tab (E5). */
class UsulanPerubahanBaru extends NotifikasiSimon
{
    protected int $pkptId;

    protected int $tahun;

    protected string $irban;

    protected ?string $konteks;

    protected string $alasan;

    public function __construct(PkptUsulan $u)
    {
        $this->pkptId = $u->pkpt_id;
        $this->tahun = (int) $u->pkpt->tahun;
        $this->irban = (string) $u->irban?->nama;
        $this->konteks = UsulanPerubahanDiputuskan::konteksUsulan($u);
        $this->alasan = $u->alasan;
    }

    public function jenis(): string
    {
        return 'usulan_perubahan_baru';
    }

    public function judul(): string
    {
        return 'Usulan perubahan PKPT baru';
    }

    public function konteks(): ?string
    {
        return $this->konteks;
    }

    public function url(): string
    {
        return route('admin.pkpt.show', ['pkpt' => $this->pkptId, 'tab' => 'perubahan'], false);
    }

    public function catatan(): ?string
    {
        return $this->alasan;
    }

    public function isi(): array
    {
        return ["{$this->irban} mengusulkan perubahan PKPT {$this->tahun}. Tampung ke Perubahan PKPT atau tutup usulan dengan catatan."];
    }
}
