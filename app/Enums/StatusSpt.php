<?php

namespace App\Enums;

/** Computed SPT status (PRD §9.2); labels and icons per DESIGN §3.6. */
enum StatusSpt: string
{
    case Draf = 'draf';
    case MenungguNomor = 'menunggu_nomor';
    case PerluRevisi = 'perlu_revisi';
    case Ditolak = 'ditolak';
    case SiapDilaksanakan = 'siap_dilaksanakan';
    case Pelaksanaan = 'pelaksanaan';
    case PenyusunanLaporan = 'penyusunan_laporan';
    case Selesai = 'selesai';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::MenungguNomor => 'Menunggu Nomor',
            self::PerluRevisi => 'Perlu Revisi',
            self::Ditolak => 'Ditolak',
            self::SiapDilaksanakan => 'Siap Dilaksanakan',
            self::Pelaksanaan => 'Pelaksanaan',
            self::PenyusunanLaporan => 'Penyusunan Laporan',
            self::Selesai => 'Selesai',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    /** Heroicon name (Selesai uses the solid variant). */
    public function icon(): string
    {
        return match ($this) {
            self::Draf => 'pencil-square',
            self::MenungguNomor => 'clock',
            self::PerluRevisi => 'arrow-uturn-left',
            self::Ditolak => 'x-circle',
            self::SiapDilaksanakan => 'calendar-days',
            self::Pelaksanaan => 'play-circle',
            self::PenyusunanLaporan => 'document-text',
            self::Selesai => 'check-circle',
            self::Dibatalkan => 'no-symbol',
        };
    }
}
