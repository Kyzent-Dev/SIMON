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

    /** x-app.tag icon + variant (rectangular tag, never a pill). */
    public function tag(): array
    {
        return match ($this) {
            self::Draf => ['icon' => 'pencil-square', 'variant' => 'neutral'],
            self::Disahkan => ['icon' => 'check-badge', 'variant' => 'mint'],
        };
    }
}
