<?php

namespace Tests\Unit;

use App\Support\MingguPkpt;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MingguPkptTest extends TestCase
{
    /** Demo PKPT 2026 Irban I: mulai, selesai, expected [bulan, minggu] pairs. */
    public static function itemDemo(): array
    {
        return [
            1 => ['2026-01-12', '2026-01-23', [[1, 2], [1, 3]]],
            2 => ['2026-01-26', '2026-01-30', [[1, 4]]],
            3 => ['2026-02-02', '2026-02-27', [[2, 1], [2, 2], [2, 3], [2, 4]]],
            4 => ['2026-03-02', '2026-03-06', [[3, 1]]],
            5 => ['2026-03-09', '2026-03-13', [[3, 2]]],
            6 => ['2026-03-23', '2026-03-27', [[3, 4]]],
            7 => ['2026-03-30', '2026-04-03', [[4, 1]]],
            8 => ['2026-04-06', '2026-04-10', [[4, 2]]],
            9 => ['2026-04-13', '2026-05-08', [[4, 3], [4, 4], [4, 5], [5, 1]]],
            10 => ['2026-05-11', '2026-05-22', [[5, 2], [5, 3]]],
            11 => ['2026-05-25', '2026-06-05', [[5, 4], [6, 1]]],
            12 => ['2026-06-08', '2026-06-26', [[6, 2], [6, 3], [6, 4]]],
            13 => ['2026-06-29', '2026-07-03', [[7, 1]]],
            14 => ['2026-07-06', '2026-07-17', [[7, 2], [7, 3]]],
            15 => ['2026-07-20', '2026-07-24', [[7, 4]]],
            16 => ['2026-07-27', '2026-08-14', [[7, 5], [8, 1], [8, 2]]],
            17 => ['2026-08-17', '2026-08-28', [[8, 3], [8, 4]]],
            18 => ['2026-08-31', '2026-09-25', [[9, 1], [9, 2], [9, 3], [9, 4]]],
            19 => ['2026-09-28', '2026-10-02', [[9, 5]]],
            20 => ['2026-10-05', '2026-10-09', [[10, 1]]],
            21 => ['2026-10-12', '2026-10-16', [[10, 2]]],
            22 => ['2026-10-19', '2026-11-13', [[10, 3], [10, 4], [11, 1], [11, 2]]],
            23 => ['2026-11-16', '2026-11-27', [[11, 3], [11, 4]]],
            24 => ['2026-11-30', '2026-12-04', [[12, 1]]],
            25 => ['2026-12-07', '2026-12-25', [[12, 2], [12, 3], [12, 4]]],
            'lebaran' => ['2026-03-16', '2026-03-20', [[3, 3]]],
            'tahun baru' => ['2026-01-01', '2026-01-02', [[1, 1]]],
            'akhir tahun' => ['2026-12-31', '2026-12-31', [[12, 5]]],
        ];
    }

    #[DataProvider('itemDemo')]
    public function test_untuk_rentang(string $mulai, string $selesai, array $harapan): void
    {
        $hasil = MingguPkpt::untukRentang(CarbonImmutable::parse($mulai), CarbonImmutable::parse($selesai), 2026);

        $this->assertSame($harapan, array_map(fn ($w) => [$w['bulan'], $w['minggu']], $hasil));
    }

    public function test_rentang_di_luar_tahun_kosong(): void
    {
        $this->assertSame([], MingguPkpt::untukRentang(CarbonImmutable::parse('2025-06-02'), CarbonImmutable::parse('2025-06-06'), 2026));
        $this->assertSame([], MingguPkpt::untukRentang(CarbonImmutable::parse('2027-02-01'), CarbonImmutable::parse('2027-02-05'), 2026));
    }

    #[DataProvider('batas')]
    public function test_dari_batas(string $tanggal, array $harapan): void
    {
        $this->assertSame($harapan, MingguPkpt::dari(CarbonImmutable::parse($tanggal)));
    }

    public static function batas(): array
    {
        return [
            ['2026-01-01', ['tahun' => 2025, 'bulan' => 12, 'minggu' => 5]],
            ['2026-01-05', ['tahun' => 2026, 'bulan' => 1, 'minggu' => 1]],
            ['2026-12-31', ['tahun' => 2026, 'bulan' => 12, 'minggu' => 5]],
            ['2026-04-01', ['tahun' => 2026, 'bulan' => 4, 'minggu' => 1]],
            ['2026-09-30', ['tahun' => 2026, 'bulan' => 9, 'minggu' => 5]],
            ['2026-03-29', ['tahun' => 2026, 'bulan' => 3, 'minggu' => 4]],
        ];
    }

    public function test_daftar_januari_empat_minggu(): void
    {
        $d = MingguPkpt::daftar(2026, 1);

        $this->assertCount(4, $d);
        $this->assertSame([1, 2, 3, 4], array_column($d, 'minggu'));
        $this->assertSame('2026-01-05', $d[0]['mulai']->toDateString());
        $this->assertSame('2026-01-09', $d[0]['selesai']->toDateString());
    }

    public function test_daftar_jumlah_minggu(): void
    {
        $this->assertCount(5, MingguPkpt::daftar(2026, 4));
        $this->assertCount(4, MingguPkpt::daftar(2026, 3));
        $this->assertCount(5, MingguPkpt::daftar(2026, 12));
    }

    /** A PKPT week is Monday–Friday: a range touching a week only on its Saturday/Sunday does not belong to it. */
    public function test_weekend_overlap_alone_is_not_a_week(): void
    {
        $minggu = fn (string $a, string $b) => array_map(fn ($w) => [$w['bulan'], $w['minggu']], MingguPkpt::untukRentang(CarbonImmutable::parse($a), CarbonImmutable::parse($b), 2026));

        $this->assertSame([[3, 3]], $minggu('2026-03-14', '2026-03-16')); // Sat 14 Mar – Mon 16 Mar: week 2 only on its Saturday
        $this->assertSame([[3, 2]], $minggu('2026-03-13', '2026-03-15')); // Fri – Sun: the Sunday adds no week
        $this->assertSame([], $minggu('2026-03-14', '2026-03-15')); // a weekend alone
        $this->assertSame([], $minggu('2026-01-03', '2026-01-04')); // not pulled into the year-edge fallback either
        $this->assertSame([[1, 1]], $minggu('2026-01-01', '2026-01-03')); // control: weekdays of the stub week still map to week 1
    }

    public function test_mutable_carbon_arguments_are_not_changed(): void
    {
        $mulai = Carbon::parse('2026-03-14 15:30');
        $selesai = Carbon::parse('2026-03-20 08:00');

        $hasil = MingguPkpt::untukRentang($mulai, $selesai, 2026);

        $this->assertSame([[3, 3]], array_map(fn ($w) => [$w['bulan'], $w['minggu']], $hasil));
        $this->assertSame(['2026-03-14 15:30:00', '2026-03-20 08:00:00'], [$mulai->toDateTimeString(), $selesai->toDateTimeString()]);
    }

    public function test_daftar_uses_jakarta_time_whatever_the_default_zone(): void
    {
        $lama = date_default_timezone_get();
        date_default_timezone_set('UTC');
        try {
            $d = MingguPkpt::daftar(2026, 1);
        } finally {
            date_default_timezone_set($lama);
        }

        $this->assertSame('Asia/Jakarta', $d[0]['mulai']->getTimezone()->getName());
        $this->assertSame('2026-01-05 00:00:00', $d[0]['mulai']->toDateTimeString());
        $this->assertSame('2026-01-09', $d[0]['selesai']->toDateString());
    }
}
