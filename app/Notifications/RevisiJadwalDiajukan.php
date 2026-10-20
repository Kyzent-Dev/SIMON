<?php

namespace App\Notifications;

use App\Models\PkptItemRevisi;
use App\Support\Tanggal;

class RevisiJadwalDiajukan extends NotifikasiSimon
{
    private string $irban;

    private string $konteks;

    private string $alasan;

    public function __construct(PkptItemRevisi $revisi)
    {
        $item = $revisi->item;
        $this->irban = $item->irban->nama;
        $this->konteks = $item->jenisPenugasan->nama.' · '.Tanggal::rentang($revisi->tanggal_mulai_semula, $revisi->tanggal_selesai_semula)
            .' → '.Tanggal::rentang($revisi->tanggal_mulai, $revisi->tanggal_selesai);
        $this->alasan = $revisi->alasan;
    }

    public function jenis(): string
    {
        return 'revisi_jadwal_diajukan';
    }

    public function judul(): string
    {
        return "Revisi jadwal PKPT diajukan oleh {$this->irban}";
    }

    public function konteks(): ?string
    {
        return $this->konteks;
    }

    public function url(): string
    {
        return route('pimpinan.revisi-jadwal', absolute: false);
    }

    public function catatan(): ?string
    {
        return $this->alasan;
    }

    public function isi(): array
    {
        return ["{$this->irban} mengajukan revisi jadwal PKPT dan menunggu persetujuan Anda.", $this->konteks];
    }
}
