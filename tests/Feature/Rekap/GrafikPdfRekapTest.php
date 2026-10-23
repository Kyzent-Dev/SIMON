<?php

namespace Tests\Feature\Rekap;

use App\Enums\Role;
use App\Models\User;
use App\Services\Ekspor\PembangunRekap;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 9.2 Task 4: HTML/CSS bar charts under the selected summary sections of the Rekap PDF. */
class GrafikPdfRekapTest extends TestCase
{
    use RefreshDatabase;
    use RekapFixture {
        setUp as private fixtureSetUp;
    }

    private User $pimpinan;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->pimpinan = User::factory()->create(['role' => Role::Pimpinan]);
    }

    private function html(array $bagian, array $filter = []): string
    {
        $p = app(PembangunRekap::class);

        return $p->html($this->pimpinan, $p->filter($this->pimpinan, ['tahun' => 2026, 'bagian' => $bagian, ...$filter]));
    }

    public function test_chart_only_under_selected_summary_sections(): void
    {
        $html = $this->html(['rr', 'detail']);
        $this->assertSame(1, substr_count($html, 'data-grafik="rr"'));
        $this->assertStringNotContainsString('data-grafik="detail"', $html);
        $this->assertStringNotContainsString('data-grafik="klasifikasi"', $html);

        $semua = $this->html(['rr', 'klasifikasi', 'jenis', 'detail']);
        $this->assertSame(3, substr_count($semua, 'data-grafik='));
    }

    public function test_paired_bar_widths_are_relative_to_the_chart_maximum(): void
    {
        $p = app(PembangunRekap::class);
        $g = $p->grafik(['irban' => ['baris' => [['nama' => 'X', 'tw' => [1 => ['rencana' => 4, 'realisasi' => 3]], 'total' => ['rencana' => 4, 'realisasi' => 3]]]],
            'klasifikasi' => ['baris' => []], 'jenis' => ['baris' => []]], ['tw' => 0, 'irban' => null, 'bagian' => ['rr']]);
        $this->assertSame([4, 3], $g['rr']['baris'][0]['nilai']);
        $this->assertSame('berpasangan', $g['rr']['jenis']);

        $html = view('pdf.partials.grafik-batang', ['kode' => 'rr', ...$g['rr']])->render();
        $this->assertStringContainsString('width: 100%', $html);
        $this->assertStringContainsString('width: 75%', $html);
    }

    public function test_jenis_is_sorted_descending_with_percent_label_and_klasifikasi_keeps_zero_rows(): void
    {
        $html = $this->html(['klasifikasi', 'jenis']);
        $this->assertLessThan(strpos($html, '33% (2/6)'), strpos($html, '67% (2/3)'));
        $this->assertStringContainsString('Berjalan 0 · Selesai 0', $html);
    }

    public function test_single_irban_charts_by_triwulan_and_empty_year_renders_a_pdf(): void
    {
        $this->assertStringContainsString('TW IV', explode('data-grafik="rr"', $this->html(['rr'], ['irban' => $this->a->id]))[1]);

        $pdf = Pdf::loadHTML($this->html(['rr', 'klasifikasi', 'jenis'], ['tahun' => 2031]))->setPaper('a4')->output();
        $this->assertStringStartsWith('%PDF', $pdf);
    }
}
