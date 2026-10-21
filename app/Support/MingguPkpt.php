<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** PKPT week rule: a Monday–Sunday week belongs to the (year, month) of its Wednesday; n = intdiv(wednesday.day - 1, 7) + 1. */
class MingguPkpt
{
    /** @return array{tahun: int, bulan: int, minggu: int} */
    public static function dari(CarbonInterface $t): array
    {
        $rabu = CarbonImmutable::instance($t)->startOfWeek(CarbonInterface::MONDAY)->addDays(2);

        return ['tahun' => $rabu->year, 'bulan' => $rabu->month, 'minggu' => intdiv($rabu->day - 1, 7) + 1];
    }

    /** @return list<array{minggu: int, mulai: CarbonImmutable, selesai: CarbonImmutable}> */
    public static function daftar(int $tahun, int $bulan): array
    {
        $rabu = CarbonImmutable::create($tahun, $bulan, 1)->startOfDay()->next(CarbonInterface::WEDNESDAY);
        if ($rabu->day > 7) { // next() skips Wednesday the 1st
            $rabu = $rabu->subWeek();
        }
        $hasil = [];
        for (; $rabu->month === $bulan; $rabu = $rabu->addWeek()) {
            $hasil[] = [
                'minggu' => intdiv($rabu->day - 1, 7) + 1,
                'mulai' => $rabu->subDays(2),
                'selesai' => $rabu->addDays(2),
            ];
        }

        return $hasil;
    }

    /** @return list<array{bulan: int, minggu: int}> */
    public static function untukRentang(CarbonInterface $mulai, CarbonInterface $selesai, int $tahun): array
    {
        $awal = CarbonImmutable::instance($mulai)->startOfDay();
        $akhir = CarbonImmutable::instance($selesai)->endOfDay();
        if ($awal->year > $tahun || $akhir->year < $tahun) {
            return [];
        }

        $hasil = [];
        for ($senin = $awal->startOfWeek(CarbonInterface::MONDAY); $senin->lte($akhir); $senin = $senin->addWeek()) {
            $w = self::dari($senin);
            if ($w['tahun'] === $tahun) {
                $hasil[] = ['bulan' => $w['bulan'], 'minggu' => $w['minggu']];
            }
        }
        if ($hasil !== []) {
            return $hasil;
        }

        // Range sits only in the stub days of the year edges that belong to the neighbouring year's weeks.
        if ($akhir->lt(self::daftar($tahun, 1)[0]['mulai'])) {
            return [['bulan' => 1, 'minggu' => 1]];
        }
        $terakhir = array_key_last($daftar = self::daftar($tahun, 12));

        return [['bulan' => 12, 'minggu' => $daftar[$terakhir]['minggu']]];
    }
}
