<?php

namespace Tests\Unit;

use App\Support\SkalaGrafik;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SkalaGrafikTest extends TestCase
{
    public static function tickProvider(): array
    {
        return [
            [0, [0, 1, 2, 3, 4]], [1, [0, 1]], [7, [0, 2, 4, 6, 8]], [10, [0, 2, 4, 6, 8, 10]],
            [13, [0, 5, 10, 15]], [99, [0, 20, 40, 60, 80, 100]], [100, [0, 20, 40, 60, 80, 100]],
            [1234, [0, 500, 1000, 1500]],
        ];
    }

    #[DataProvider('tickProvider')]
    public function test_nice_ticks(int $maks, array $harapan): void
    {
        $this->assertSame($harapan, SkalaGrafik::tick($maks));
    }

    public function test_heights_are_proportional_and_capped(): void
    {
        $this->assertSame([140, 70, 0], SkalaGrafik::batang([8, 4, 0], 8, false));
        $this->assertSame([140], SkalaGrafik::batang([20], 8, false)); // capped
        $this->assertSame([2], SkalaGrafik::batang([1], 1000, false)); // a non-zero value stays visible
        $this->assertSame([0, 0], SkalaGrafik::batang([0, 0], 0, false));
    }

    public function test_stacked_segments_sum_to_the_stack_height(): void
    {
        $h = SkalaGrafik::batang([3, 5], 8, true);
        $this->assertSame(140, array_sum($h));
        $this->assertSame([53, 87], $h);
        $this->assertSame([0, 70], SkalaGrafik::batang([0, 4], 8, true));
        $this->assertSame([0, 0], SkalaGrafik::batang([0, 0], 4, true));
    }

    public function test_gridline_offsets_and_width(): void
    {
        $this->assertSame([0, 70, 140], array_column(SkalaGrafik::garis([0, 1, 2]), 'bawah'));
        $this->assertSame(40, SkalaGrafik::lebar(1));
        $this->assertSame(100, SkalaGrafik::lebar(5));
    }
}
