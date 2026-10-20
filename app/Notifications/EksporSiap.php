<?php

namespace App\Notifications;

use App\Models\Ekspor;

class EksporSiap extends NotifikasiSimon
{
    private string $namaFile;

    private string $eksporId;

    public function __construct(Ekspor $ekspor)
    {
        $this->namaFile = $ekspor->nama_file;
        $this->eksporId = $ekspor->id;
    }

    public function jenis(): string
    {
        return 'ekspor_siap';
    }

    public function judul(): string
    {
        return 'File ekspor siap';
    }

    public function konteks(): ?string
    {
        return $this->namaFile;
    }

    public function url(): string
    {
        return route('ekspor.unduh', $this->eksporId, absolute: false);
    }

    public function isi(): array
    {
        return ["File ekspor {$this->namaFile} sudah siap diunduh. File tersedia selama 7 hari."];
    }

    protected function labelTombol(): string
    {
        return 'Unduh';
    }
}
