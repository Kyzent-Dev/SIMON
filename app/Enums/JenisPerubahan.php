<?php

namespace App\Enums;

enum JenisPerubahan: string
{
    case Perubahan = 'perubahan';
    case KoreksiInput = 'koreksi_input';

    public function label(): string
    {
        return match ($this) {
            self::Perubahan => 'Perubahan',
            self::KoreksiInput => 'Koreksi input',
        };
    }
}
