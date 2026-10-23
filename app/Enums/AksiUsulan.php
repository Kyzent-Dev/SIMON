<?php

namespace App\Enums;

enum AksiUsulan: string
{
    case Geser = 'geser';
    case Hapus = 'hapus';
    case Tambah = 'tambah';
    case Lainnya = 'lainnya';

    public function label(): string
    {
        return match ($this) {
            self::Geser => 'Geser jadwal',
            self::Hapus => 'Hapus item',
            self::Tambah => 'Item baru',
            self::Lainnya => 'Lainnya',
        };
    }
}
