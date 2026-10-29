<?php

namespace App\Support;

use App\Services\Settings;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class Tanggal
{
    /** Compact Indonesian range: "1 April 2026" (one day), "3–21 Februari 2026", "28 Februari – 3 Maret 2026", "30 Desember 2026 – 2 Januari 2027". */
    public static function rentang(DateTimeInterface $mulai, DateTimeInterface $selesai): string
    {
        $m = CarbonImmutable::instance($mulai);
        $s = CarbonImmutable::instance($selesai);
        if ($m->isSameDay($s)) {
            return $s->translatedFormat('j F Y');
        }
        if ($m->year !== $s->year) {
            return $m->translatedFormat('j F Y').' – '.$s->translatedFormat('j F Y');
        }
        if ($m->month !== $s->month) {
            return $m->translatedFormat('j F').' – '.$s->translatedFormat('j F Y');
        }

        return $m->format('j').'–'.$s->translatedFormat('j F Y');
    }

    /**
     * Rekap Tahunan ribbon (Bagian Umum stambook wording, two-digit days): "03 Februari 2025", "03 s.d 07 Februari 2025",
     * "28 Januari s.d 03 Februari 2025", "29 Desember 2025 s.d 02 Januari 2026".
     */
    public static function pita(DateTimeInterface $mulai, DateTimeInterface $selesai): string
    {
        $m = CarbonImmutable::instance($mulai);
        $s = CarbonImmutable::instance($selesai);
        $akhir = $s->translatedFormat('d F Y');
        if ($m->isSameDay($s)) {
            return $akhir;
        }
        $awal = $m->translatedFormat(match (true) {
            $m->year !== $s->year => 'd F Y',
            $m->month !== $s->month => 'd F',
            default => 'd',
        });

        return "{$awal} s.d {$akhir}";
    }

    /** Assignment duration: kalender or kerja per `penugasan.hitungan_hari` (one place for Spt::durasi() and the form). */
    public static function durasiPenugasan(DateTimeInterface|string $mulai, DateTimeInterface|string $selesai): int
    {
        return self::durasi($mulai, $selesai, app(Settings::class)->get('penugasan.hitungan_hari') === 'kerja');
    }

    /** Inclusive day count; $kerja counts Monday–Friday only (no holiday calendar). */
    public static function durasi(DateTimeInterface|string $mulai, DateTimeInterface|string $selesai, bool $kerja = false): int
    {
        $m = CarbonImmutable::parse($mulai)->startOfDay();
        $s = CarbonImmutable::parse($selesai)->startOfDay();
        if (! $kerja) {
            return (int) $m->diffInDays($s) + 1;
        }
        $hari = 0;
        for ($d = $m; $d->lte($s); $d = $d->addDay()) {
            $hari += $d->isWeekday() ? 1 : 0;
        }

        return $hari;
    }

    /** Import cell date (Excel serial, dd/mm/yyyy or yyyy-mm-dd) as Y-m-d; null when the format is not recognised. */
    public static function dariSel(string $v): ?string
    {
        if (preg_match('/^\d{1,6}(\.\d+)?$/', $v)) { // Excel date serial
            $serial = (float) $v;

            return $serial >= 1 && $serial < 2958466 ? Date::excelToDateTimeObject(floor($serial))->format('Y-m-d') : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $v, $m)) {
            [, $d, $mo, $y] = $m;
        } elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m)) {
            [, $y, $mo, $d] = $m;
        } else {
            return null;
        }

        return checkdate((int) $mo, (int) $d, (int) $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }
}
