<?php

namespace App\Enums;

enum JenisObjek: string
{
    case Kecamatan = 'kecamatan';
    case Desa = 'desa';
    case Sekolah = 'sekolah';
    case Puskesmas = 'puskesmas';
    case Rsud = 'rsud';
    case Bumdes = 'bumdes';
    case DinasBadan = 'dinas_badan';
    case Bumd = 'bumd';

    public function label(): string
    {
        return match ($this) {
            self::Kecamatan => 'Kecamatan',
            self::Desa => 'Desa',
            self::Sekolah => 'Sekolah',
            self::Puskesmas => 'Puskesmas',
            self::Rsud => 'RSUD',
            self::Bumdes => 'BUMDes',
            self::DinasBadan => 'Dinas/Badan',
            self::Bumd => 'BUMD',
        };
    }

    /** Desa, sekolah, puskesmas and BUMDes hang under a kecamatan. */
    public function needsParent(): bool
    {
        return in_array($this, self::childTypes(), true);
    }

    /** @return list<self> */
    public static function childTypes(): array
    {
        return [self::Desa, self::Sekolah, self::Puskesmas, self::Bumdes];
    }

    /** Jenis that can be assigned to an irban. @return list<self> */
    public static function wilayahTypes(): array
    {
        return [self::Kecamatan, self::DinasBadan, self::Bumd, self::Rsud];
    }

    /** @param list<self>|null $cases @return array<string, string> value => label */
    public static function options(?array $cases = null): array
    {
        $options = [];
        foreach ($cases ?? self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
