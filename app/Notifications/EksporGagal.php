<?php

namespace App\Notifications;

class EksporGagal extends NotifikasiSimon
{
    public function __construct(private string $namaFile, private string $halaman) {}

    public function jenis(): string
    {
        return 'ekspor_gagal';
    }

    public function judul(): string
    {
        return 'Ekspor gagal';
    }

    public function konteks(): ?string
    {
        return $this->namaFile;
    }

    public function url(): string
    {
        return $this->halaman;
    }

    public function isi(): array
    {
        return ["File ekspor {$this->namaFile} gagal dibuat. Silakan coba lagi."];
    }
}
