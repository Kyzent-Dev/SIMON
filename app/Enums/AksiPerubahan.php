<?php

namespace App\Enums;

enum AksiPerubahan: string
{
    case Tambah = 'tambah';
    case Ubah = 'ubah';
    case Hapus = 'hapus';

    public function label(): string
    {
        return match ($this) {
            self::Tambah => 'Tambah item',
            self::Ubah => 'Ubah item',
            self::Hapus => 'Hapus item',
        };
    }
}
