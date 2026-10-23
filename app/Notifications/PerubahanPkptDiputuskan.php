<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\PkptPerubahan;
use Illuminate\Notifications\Messages\MailMessage;

/** Approved: Admin opens the package, Pimpinan its detail, Irban its schedule (E5). Rejected: only the Admin creator. */
class PerubahanPkptDiputuskan extends NotifikasiSimon
{
    protected int $perubahanId;

    protected int $pkptId;

    protected int $tahun;

    protected ?int $nomor;

    protected ?string $komentar;

    public function __construct(PkptPerubahan $p, protected bool $setujui)
    {
        $this->perubahanId = $p->id;
        $this->pkptId = $p->pkpt_id;
        $this->tahun = (int) $p->pkpt->tahun;
        $this->nomor = $p->nomor_urut;
        $this->komentar = $p->komentar_putusan;
    }

    public function jenis(): string
    {
        return $this->setujui ? 'perubahan_pkpt_disetujui' : 'perubahan_pkpt_ditolak';
    }

    public function judul(): string
    {
        return $this->setujui ? 'Perubahan PKPT disetujui' : 'Perubahan PKPT ditolak';
    }

    public function konteks(): ?string
    {
        return collect(["PKPT {$this->tahun}", $this->nomor ? "Perubahan ke-{$this->nomor}" : null])->filter()->implode(' · ');
    }

    public function url(): string
    {
        return $this->urlUntuk(Role::Admin);
    }

    public function catatan(): ?string
    {
        return $this->komentar;
    }

    public function isi(): array
    {
        return [$this->setujui
            ? 'Pimpinan menyetujui perubahan PKPT. Jadwal baru sudah berlaku.'
            : 'Pimpinan menolak perubahan PKPT. Perbaiki lalu ajukan kembali.'];
    }

    public function toArray(object $notifiable): array
    {
        return array_merge(parent::toArray($notifiable), ['url' => $this->urlUntuk($notifiable->role ?? null)]);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return parent::toMail($notifiable)->action($this->labelTombol(), url($this->urlUntuk($notifiable->role ?? null)));
    }

    private function urlUntuk(?Role $peran): string
    {
        return match ($peran) {
            Role::Irban => route('irban.pkpt', absolute: false),
            Role::Pimpinan => route('pimpinan.perubahan-pkpt.show', ['perubahan' => $this->perubahanId], false),
            default => route('admin.pkpt.perubahan', ['pkpt' => $this->pkptId, 'perubahan' => $this->perubahanId], false),
        };
    }
}
