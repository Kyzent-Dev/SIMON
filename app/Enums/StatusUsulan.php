<?php

namespace App\Enums;

enum StatusUsulan: string
{
    case Terbuka = 'terbuka';
    case Ditampung = 'ditampung';
    case Diterapkan = 'diterapkan';
    case Ditutup = 'ditutup';

    public function label(): string
    {
        return match ($this) {
            self::Terbuka => 'Terbuka',
            self::Ditampung => 'Ditampung',
            self::Diterapkan => 'Diterapkan',
            self::Ditutup => 'Ditutup',
        };
    }

    /** x-app.tag icon + variant (rectangular tag, never a pill). */
    public function tag(): array
    {
        return match ($this) {
            self::Terbuka => ['icon' => 'inbox', 'variant' => 'info'],
            self::Ditampung => ['icon' => 'arrow-down-on-square', 'variant' => 'warning'],
            self::Diterapkan => ['icon' => 'check', 'variant' => 'mint'],
            self::Ditutup => ['icon' => 'x-circle', 'variant' => 'neutral'],
        };
    }
}
