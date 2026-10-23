<?php

namespace App\Enums;

enum StatusPerubahan: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Diajukan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    /** x-app.tag icon + variant (rectangular tag, never a pill). */
    public function tag(): array
    {
        return match ($this) {
            self::Draf => ['icon' => 'pencil-square', 'variant' => 'neutral'],
            self::Diajukan => ['icon' => 'clock', 'variant' => 'warning'],
            self::Disetujui => ['icon' => 'check', 'variant' => 'mint'],
            self::Ditolak => ['icon' => 'x-circle', 'variant' => 'danger'],
        };
    }

    /** Only one open package (draf, diajukan or ditolak) per PKPT. */
    public function terbuka(): bool
    {
        return $this !== self::Disetujui;
    }
}
