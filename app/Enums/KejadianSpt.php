<?php

namespace App\Enums;

/** `spt_riwayat.kejadian` values. Plain string constants (not an enum cast) so later phases can add events freely. */
final class KejadianSpt
{
    public const DIBUAT = 'dibuat';

    public const DIUBAH = 'diubah';

    public const DIAJUKAN = 'diajukan';

    public const PERLU_REVISI = 'perlu_revisi';

    public const DITOLAK = 'ditolak';

    public const DITERIMA = 'diterima';

    public const DICATAT_MANUAL = 'dicatat_manual';

    public const DIBATALKAN = 'dibatalkan';

    public const NOMOR_AWAL = 'nomor_awal';

    public const TANGGAL_DIUBAH = 'tanggal_diubah';

    public const FINAL_DIUNGGAH = 'final_diunggah';

    public const DITANDAI_SELESAI = 'ditandai_selesai';

    public const KENDALA_DIISI = 'kendala_diisi';

    private const LABELS = [
        self::DIBUAT => 'Dibuat',
        self::DIUBAH => 'Diubah',
        self::DIAJUKAN => 'Diajukan',
        self::PERLU_REVISI => 'Perlu Revisi',
        self::DITOLAK => 'Ditolak',
        self::DITERIMA => 'Nomor diterbitkan',
        self::DICATAT_MANUAL => 'Nomor dicatat manual',
        self::DIBATALKAN => 'Nomor dibatalkan',
        self::NOMOR_AWAL => 'Nomor awal diatur',
        self::TANGGAL_DIUBAH => 'Tanggal realisasi diubah',
        self::FINAL_DIUNGGAH => 'SPT final diunggah',
        self::DITANDAI_SELESAI => 'Ditandai Selesai',
        self::KENDALA_DIISI => 'Kendala diisi',
    ];

    /** Riwayat step label; unknown values fall back to the raw value. */
    public static function label(string $kejadian): string
    {
        return self::LABELS[$kejadian] ?? $kejadian;
    }
}
