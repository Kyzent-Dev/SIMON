<?php

namespace Tests\Unit;

use App\Support\Tanggal;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Rekap Tahunan date ribbon (spec 1.1.4 Task 4): the four formats of the Bagian Umum file. */
class PitaTanggalTest extends TestCase
{
    private string $locale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locale = Carbon::getLocale();
        Carbon::setLocale('id');
    }

    protected function tearDown(): void
    {
        Carbon::setLocale($this->locale);
        parent::tearDown();
    }

    public static function kasus(): array
    {
        return [
            'sebulan' => ['2025-02-03', '2025-02-07', '03 s.d 07 Februari 2025'],
            'satu hari' => ['2025-02-03', '2025-02-03', '03 Februari 2025'],
            'lintas bulan' => ['2025-01-28', '2025-02-03', '28 Januari s.d 03 Februari 2025'],
            'lintas tahun' => ['2025-12-29', '2026-01-02', '29 Desember 2025 s.d 02 Januari 2026'],
        ];
    }

    #[DataProvider('kasus')]
    public function test_format(string $mulai, string $selesai, string $harapan): void
    {
        $this->assertSame($harapan, Tanggal::pita(CarbonImmutable::parse($mulai), CarbonImmutable::parse($selesai)));
    }
}
