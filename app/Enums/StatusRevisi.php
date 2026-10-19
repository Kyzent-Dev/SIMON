<?php

namespace App\Enums;

enum StatusRevisi: string
{
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';
    case Ditolak = 'ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Menunggu => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    /** x-app.tag icon + variant (rectangular tag, never a pill). */
    public function tag(): array
    {
        return match ($this) {
            self::Menunggu => ['icon' => 'clock', 'variant' => 'warning'],
            self::Disetujui => ['icon' => 'check', 'variant' => 'mint'],
            self::Ditolak => ['icon' => 'x-circle', 'variant' => 'danger'],
        };
    }
}
