<?php

namespace App\Enums;

enum SumberSpt: string
{
    case Sistem = 'sistem';
    case Manual = 'manual';
    case Impor = 'impor';

    public function label(): string
    {
        return match ($this) {
            self::Sistem => 'Sistem',
            self::Manual => 'Manual',
            self::Impor => 'Impor',
        };
    }
}
