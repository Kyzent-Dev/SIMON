<?php

namespace App\Enums;

enum StatusItemPkpt: string
{
    case Aktif = 'aktif';
    case Dihapus = 'dihapus';

    public function label(): string
    {
        return match ($this) {
            self::Aktif => 'Aktif',
            self::Dihapus => 'Dihapus',
        };
    }
}
