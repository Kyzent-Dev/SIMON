<?php

namespace App\Enums;

enum StatusPermintaan: string
{
    case Terbuka = 'terbuka';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Selesai => 'Selesai',
        };
    }
}
