<?php

namespace Tests\Feature\Publik;

use App\Services\Publik\RealisasiPublik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BerandaRingkasanTest extends TestCase
{
    use PublikFixture;
    use RefreshDatabase;

    public function test_stats_equal_the_public_service(): void
    {
        // PublikFixture, today 15 Desember 2026: items a1 (target 2) + b1 (target null = 1) → item 2, rencana 3;
        // public SPT v1 + v2 → selesai 2; persen = round(2 / 3 * 100, 1) = 66,7.
        $r = app(RealisasiPublik::class)->ringkasan(['tahun' => 2026]);
        $this->assertSame(['selesai' => 2, 'item' => 2, 'rencana' => 3, 'persen' => 66.7], $r);

        $html = $this->withoutVite()->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Item PKPT 2026', $html);
        $this->assertMatchesRegularExpression('/x-data="countUp\(2, 0\)"[^>]*>2<\/span>(<\/span>)?<span class="sr-only">2<\/span>/', $html);
        $this->assertStringContainsString('x-data="countUp(66.7, 1)"', $html);
        $this->assertStringContainsString('<span class="sr-only">66,7%</span>', $html);
        $this->assertStringContainsString('2 dari 3 SPT yang direncanakan', $html);
        $this->assertStringContainsString('style="width: 66.7%"', $html); // x-ui.progress fill
        $this->assertStringContainsString('Data per 15 Desember 2026', $html);
        $this->assertStringContainsString('data-motion="stagger"', $html);
        $this->assertStringNotContainsString('RAHASIA', $html);
        $this->assertStringNotContainsString('FACTORY/', $html);
    }

    public function test_progress_bar_clamps_at_100(): void
    {
        $html = $this->blade('<x-ui.progress :nilai="5" :maks="4" />');
        $html->assertSee('style="width: 100%"', false)->assertSee('5 dari 4 SPT selesai');
        $this->blade('<x-ui.progress :nilai="0" :maks="0" />')->assertSee('style="width: 0%"', false);
    }
}
