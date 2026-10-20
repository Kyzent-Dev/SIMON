<?php

namespace App\Notifications;

use App\Enums\StatusRevisi;
use App\Models\PkptItemRevisi;
use App\Support\Tanggal;

class RevisiJadwalDiputuskan extends NotifikasiSimon
{
    private bool $setuju;

    private string $konteks;

    private ?string $komentar;

    public function __construct(PkptItemRevisi $revisi)
    {
        $this->setuju = $revisi->status === StatusRevisi::Disetujui;
        $this->konteks = $revisi->item->jenisPenugasan->nama.' · '.Tanggal::rentang($revisi->tanggal_mulai, $revisi->tanggal_selesai);
        $this->komentar = $revisi->komentar;
    }

    public function jenis(): string
    {
        return $this->setuju ? 'revisi_jadwal_disetujui' : 'revisi_jadwal_ditolak';
    }

    public function judul(): string
    {
        return $this->setuju ? 'Revisi jadwal PKPT disetujui' : 'Revisi jadwal PKPT ditolak';
    }

    public function konteks(): ?string
    {
        return $this->konteks;
    }

    public function url(): string
    {
        return route('irban.pkpt', absolute: false);
    }

    public function catatan(): ?string
    {
        return $this->komentar;
    }

    public function isi(): array
    {
        return [$this->judul().'.', $this->konteks];
    }
}
