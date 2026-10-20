<?php

namespace App\Notifications;

use App\Models\Spt;
use Illuminate\Support\Str;

class PengajuanBaru extends NotifikasiSimon
{
    private string $irban;

    private string $konteks;

    private int $sptId;

    public function __construct(Spt $spt)
    {
        $this->irban = $spt->irban->nama;
        $this->konteks = $spt->jenisPenugasan?->nama.' · '.Str::limit((string) $spt->uraian_untuk, 60);
        $this->sptId = $spt->id;
    }

    public function jenis(): string
    {
        return 'pengajuan_baru';
    }

    public function judul(): string
    {
        return "Pengajuan nomor SPT baru dari {$this->irban}";
    }

    public function konteks(): ?string
    {
        return $this->konteks;
    }

    public function url(): string
    {
        return route('bagian-umum.pengajuan', ['pilih' => $this->sptId], absolute: false);
    }

    public function isi(): array
    {
        return ["{$this->irban} mengajukan nomor SPT baru.", $this->konteks];
    }
}
