<?php

namespace Tests\Feature\Rekap;

use App\Enums\Role;
use App\Models\User;
use App\Services\Ekspor\PembangunRekap;
use App\Support\PaletGrafik;
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
        $this->assertStringContainsString('Rencana 4', $html);
        $this->assertStringContainsString('Realisasi 3', $html);
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

    private function rekapPalsu(array $klasifikasi = [], array $jenis = []): array
    {
        return ['irban' => ['baris' => []], 'klasifikasi' => ['baris' => $klasifikasi], 'jenis' => ['baris' => $jenis]];
    }

    /** The percent bars have a fixed 0–100 scale (not relative to the longest bar), and a zero draws no bar at all (its border would be a 1px sliver). */
    public function test_jenis_bars_use_the_scale_of_100_and_a_zero_value_draws_no_bar(): void
    {
        $g = app(PembangunRekap::class)->grafik($this->rekapPalsu(jenis: [
            ['nama' => 'Setengah', 'total' => ['rencana' => 4, 'realisasi' => 2]],
            ['nama' => 'Nol', 'total' => ['rencana' => 4, 'realisasi' => 0]],
            ['nama' => 'Tanpa rencana', 'total' => ['rencana' => 0, 'realisasi' => 0]],
        ]), ['tw' => 0, 'irban' => null, 'bagian' => ['jenis']]);

        $html = view('pdf.partials.grafik-batang', ['kode' => 'jenis', ...$g['jenis']])->render();

        $this->assertSame(1, substr_count($html, 'width: 50%; height: 10px')); // 50 of 100, not 100% of the longest bar
        $this->assertStringNotContainsString('width: 100%; height: 10px', $html);
        $this->assertSame(1, substr_count($html, 'height: 10px')); // only "Setengah" has a bar
        $this->assertStringContainsString('0% (0/4)', $html); // the zero row keeps its label and number
        $this->assertStringNotContainsString('Tanpa rencana', $html);
    }

    public function test_paired_zero_value_draws_no_bar_for_that_series_only(): void
    {
        $html = view('pdf.partials.grafik-batang', ['kode' => 'rr', 'jenis' => 'berpasangan', 'judul' => 'x', 'legenda' => [
            ['label' => 'Rencana', 'isi' => '#111111', 'garis' => '#222222'], ['label' => 'Realisasi', 'isi' => '#333333', 'garis' => '#444444'],
        ], 'baris' => [['label' => 'A', 'nilai' => [4, 0], 'teks' => ['Rencana 4', 'Realisasi 0']]]])->render();

        $this->assertSame(1, substr_count($html, 'height: 10px'));
        $this->assertStringContainsString('Realisasi 0', $html);
    }

    /** Stacked bar: the outer width is the row total against the chart maximum, each segment is its share of the row, and a zero segment is left out. */
    public function test_stacked_bar_widths_and_zero_segments(): void
    {
        $g = app(PembangunRekap::class)->grafik($this->rekapPalsu(klasifikasi: [
            ['nama' => 'X', 'total' => ['berjalan' => 1, 'selesai' => 3]],
            ['nama' => 'Y', 'total' => ['berjalan' => 2, 'selesai' => 0]],
        ]), ['tw' => 0, 'irban' => null, 'bagian' => ['klasifikasi']]);
        $this->assertSame('bertumpuk', $g['klasifikasi']['jenis']);

        $html = view('pdf.partials.grafik-batang', ['kode' => 'klasifikasi', ...$g['klasifikasi']])->render();

        $this->assertSame(1, substr_count($html, '<div style="width: 100%;">')); // X: 4 of the maximum 4
        $this->assertSame(1, substr_count($html, '<div style="width: 50%;">'));  // Y: 2 of 4
        $this->assertSame(1, substr_count($html, 'width: 25%; height: 10px')); // X berjalan 1 of 4
        $this->assertSame(1, substr_count($html, 'width: 75%; height: 10px')); // X selesai 3 of 4
        $this->assertSame(1, substr_count($html, 'width: 100%; height: 10px')); // Y: only berjalan, the whole row
        $this->assertSame(3, substr_count($html, 'height: 10px')); // Y's zero selesai has no segment
    }

    public function test_pdf_text_colours_come_from_the_palette_constants(): void
    {
        $html = $this->html(['rr']);

        $this->assertStringContainsString('color: '.PaletGrafik::TEKS.';', $html);
        $this->assertStringContainsString('color: '.PaletGrafik::TEKS_SEKUNDER.';', $html);
        $this->assertStringNotContainsString(PaletGrafik::TEKS, file_get_contents(resource_path('views/pdf/rekap-triwulan.blade.php')));
    }
}
