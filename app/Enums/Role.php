<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Pimpinan = 'pimpinan';
    case Irban = 'irban';
    case BagianUmum = 'bagian_umum';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Pimpinan => 'Pimpinan',
            self::Irban => 'Irban',
            self::BagianUmum => 'Bagian Umum',
        };
    }

    public function dashboardRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Pimpinan => 'pimpinan.dashboard',
            self::Irban => 'irban.dashboard',
            self::BagianUmum => 'bagian-umum.dashboard',
        };
    }
}
