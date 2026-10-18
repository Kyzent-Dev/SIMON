<?php

namespace Tests\Unit;

use App\Services\ColorRules;
use PHPUnit\Framework\TestCase;

class ColorRulesTest extends TestCase
{
    /** DESIGN §3.7 table: id => [vs white, label ratio]. */
    private const TABLE = [
        1 => [12.17, 12.17], 2 => [3.33, 5.20], 3 => [5.20, 5.20], 4 => [3.66, 4.73], 5 => [5.88, 5.88], 6 => [3.35, 5.17],
        7 => [5.26, 5.26], 8 => [5.09, 5.09], 9 => [3.65, 4.74], 10 => [8.73, 8.73], 11 => [7.65, 7.65], 12 => [7.10, 7.10],
    ];

    public function test_twelve_swatches_match_design_table(): void
    {
        $this->assertCount(12, ColorRules::SWATCHES);
        foreach (ColorRules::SWATCHES as $s) {
            [$white, $labelRatio] = self::TABLE[$s['id']];
            $this->assertEqualsWithDelta($white, ColorRules::contrast($s['hex'], '#FFFFFF'), 0.05, $s['nama']);
            $this->assertGreaterThanOrEqual(3.0, ColorRules::contrast($s['hex'], '#FFFFFF'));
            $this->assertEqualsWithDelta($labelRatio, ColorRules::contrast($s['hex'], $s['label']), 0.05, $s['nama']);
            $this->assertSame($s['label'], ColorRules::labelFor($s['hex']), $s['nama']);
            $this->assertSame([], ColorRules::validateCustom($s['hex']), $s['nama']);
        }
    }

    public function test_contrast_and_delta_e_basics(): void
    {
        $this->assertEqualsWithDelta(21.0, ColorRules::contrast('#000000', '#FFFFFF'), 0.01);
        $this->assertEqualsWithDelta(0.0, ColorRules::deltaE76('#17735B', '#17735B'), 0.001);
        $this->assertEqualsWithDelta(100.0, ColorRules::deltaE76('#000000', '#FFFFFF'), 0.1);
    }

    public function test_rejections_use_exact_messages(): void
    {
        $this->assertContains('Kontras warna terhadap putih minimal 3:1.', ColorRules::validateCustom('#FFFF99'));
        $this->assertContains('Warna terlalu mirip dengan warna utama/Selesai.', ColorRules::validateCustom('#2A8A70'));
        $this->assertContains('Warna terlalu mirip dengan warna utama/Selesai.', ColorRules::validateCustom('#1D6B34'));
        $this->assertNull(ColorRules::labelFor('#808080'));
        $this->assertContains('Warna terlalu terang/gelap untuk label teks', ColorRules::validateCustom('#808080'));
        $this->assertNotEmpty(ColorRules::validateCustom('bukan-hex'));
    }

    public function test_normalize_expands_shorthand_and_uppercases(): void
    {
        $this->assertSame('#AABBCC', ColorRules::normalize('abc'));
        $this->assertSame('#AABBCC', ColorRules::normalize(' #aabbcc '));
        $this->assertNull(ColorRules::normalize('#12345'));
    }
}
