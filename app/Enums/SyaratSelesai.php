<?php

namespace App\Enums;

enum SyaratSelesai: string
{
    case LhpLengkap = 'lhp_lengkap';
    case Konfirmasi = 'konfirmasi';
    case LaporanOpsional = 'laporan_opsional';
    case DokumenOpsional = 'dokumen_opsional';

    public function label(): string
    {
        return match ($this) {
            self::LhpLengkap => 'Cover LHP + nomor + tanggal LHP',
            self::Konfirmasi => 'Tanpa dokumen, cukup konfirmasi',
            self::LaporanOpsional => 'Laporan perjalanan opsional',
            self::DokumenOpsional => 'Dokumen opsional',
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
