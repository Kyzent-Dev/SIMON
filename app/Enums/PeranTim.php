<?php

namespace App\Enums;

enum PeranTim: string
{
    case SupervisorDalnis = 'supervisor_dalnis';
    case Ketua = 'ketua';
    case Anggota = 'anggota';

    public function label(): string
    {
        return match ($this) {
            self::SupervisorDalnis => 'Supervisor/Dalnis',
            self::Ketua => 'Ketua',
            self::Anggota => 'Anggota',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
