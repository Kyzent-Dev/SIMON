<?php

namespace App\Support;

use App\Services\Settings;
use Carbon\CarbonImmutable;
use DateTimeInterface;

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
}
