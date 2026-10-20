<?php

namespace App\Support;

/** Icon tile per notification `jenis` (heroicons outline + tone), shared by the bell and the list page. */
class JenisNotifikasi
{
    private const IKON = [
        'pengajuan_baru' => ['ikon' => 'inbox-arrow-down', 'nada' => 'info'],
        'pengajuan_diterima' => ['ikon' => 'check-circle', 'nada' => 'success'],
        'pengajuan_perlu_revisi' => ['ikon' => 'pencil-square', 'nada' => 'warning'],
        'pengajuan_ditolak' => ['ikon' => 'x-circle', 'nada' => 'danger'],
        'nomor_dibatalkan' => ['ikon' => 'no-symbol', 'nada' => 'danger'],
        'revisi_jadwal_diajukan' => ['ikon' => 'calendar-days', 'nada' => 'info'],
        'revisi_jadwal_disetujui' => ['ikon' => 'calendar-days', 'nada' => 'success'],
        'revisi_jadwal_ditolak' => ['ikon' => 'calendar-days', 'nada' => 'danger'],
        'spt_terlambat' => ['ikon' => 'clock', 'nada' => 'danger'],
        'pengingat_spt_final' => ['ikon' => 'document-arrow-up', 'nada' => 'warning'],
        'ringkasan_mingguan' => ['ikon' => 'chart-bar', 'nada' => 'neutral'],
    ];

    /** @return array{ikon: string, nada: string} */
    public static function ikon(string $jenis): array
    {
        return self::IKON[$jenis] ?? ['ikon' => 'bell', 'nada' => 'neutral'];
    }
}
