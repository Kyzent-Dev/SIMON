<?php

namespace App\Enums;

enum StatusPkpt: string
{
    case Draf = 'draf';
    case Disahkan = 'disahkan';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Disahkan => 'Disahkan',
        };
    }
}
