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

    public function test_paired_bar_heights_are_relative_to_the_axis_top(): void
    {
        $p = app(PembangunRekap::class);
        $g = $p->grafik(['irban' => ['baris' => [['nama' => 'X', 'tw' => [1 => ['rencana' => 4, 'realisasi' => 3]], 'total' => ['rencana' => 4, 'realisasi' => 3]]]],
            'klasifikasi' => ['baris' => []], 'jenis' => ['baris' => []]], ['tw' => 0, 'irban' => null, 'bagian' => ['rr']]);
        $this->assertSame([4, 3], $g['rr']['baris'][0]['nilai']);
        $this->assertSame('berpasangan', $g['rr']['jenis']);

        $html = view('pdf.partials.grafik-batang', ['kode' => 'rr', ...$g['rr']])->render();
        $this->assertStringContainsString('height: 139px', $html); // 4 of axis top 4 (140px, minus the 1px top border)
        $this->assertStringContainsString('height: 104px', $html); // 3 of 4
        $this->assertSame(5, substr_count($html, 'class="grafik-y"')); // ticks 0..4
        $this->assertStringContainsString('class="titik"', $html);
        $this->assertStringContainsString('Realisasi', $html);
    }

    public function test_jenis_is_sorted_descending_with_percent_label_and_klasifikasi_keeps_zero_rows(): void
    {
        $html = $this->html(['klasifikasi', 'jenis']);
        $this->assertLessThan(strpos($html, '33% (2/6)'), strpos($html, '67% (2/3)'));
        $grafik = explode('data-grafik="jenis"', explode('data-grafik="klasifikasi"', $html)[1])[0];
        $this->assertStringContainsString('>DIKLAT</td>', $grafik); // zero row keeps its category label
        $this->assertSame(3, substr_count($grafik, 'class="grafik-nilai">0<')); // DIKLAT, LUAR DAERAH, LAIN-LAIN show 0
        $this->assertSame(4, substr_count($grafik, 'class="grafik-batang"')); // only REGULER and KHUSUS draw segments
        $this->assertStringContainsString('grafik-tabel', $html); // Per Jenis stays horizontal
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

        $this->assertSame(1, substr_count($html, 'class="grafik-batang"'));
        $this->assertSame(2, substr_count($html, 'class="grafik-nilai"')); // the 0 keeps its number above the axis
    }

    /** Stacked bar: segments are slices of one stack scaled to the axis top; a zero segment is left out. */
    public function test_stacked_bar_heights_and_zero_segments(): void
    {
        $g = app(PembangunRekap::class)->grafik($this->rekapPalsu(klasifikasi: [
            ['nama' => 'X', 'total' => ['berjalan' => 1, 'selesai' => 3]],
            ['nama' => 'Y', 'total' => ['berjalan' => 2, 'selesai' => 0]],
        ]), ['tw' => 0, 'irban' => null, 'bagian' => ['klasifikasi']]);
        $this->assertSame('bertumpuk', $g['klasifikasi']['jenis']);

        $html = view('pdf.partials.grafik-batang', ['kode' => 'klasifikasi', ...$g['klasifikasi']])->render();

        $this->assertStringContainsString('height: 34px', $html);  // X berjalan 1 of 4 = 35px
        $this->assertStringContainsString('height: 104px', $html); // X selesai: stack 140 - 35 = 105px
        $this->assertStringContainsString('height: 69px', $html);  // Y: 2 of 4 = 70px
        $this->assertSame(3, substr_count($html, 'class="grafik-batang"')); // Y's zero selesai has no segment
    }

    private function render(string $jenis, array $baris, array $legenda): string
    {
        $html = view('pdf.partials.grafik-batang', ['kode' => 'x', 'jenis' => $jenis, 'judul' => 'T', 'baris' => $baris, 'legenda' => $legenda])->render();
        $this->assertStringStartsWith('%PDF', Pdf::loadHTML('<html><body>'.$html.'</body></html>')->setPaper('a4')->output());

        return $html;
    }

    public function test_vertical_chart_renders_in_dompdf_for_empty_single_and_five_categories(): void
    {
        $rr = [['label' => 'Rencana', 'isi' => '#E6ECEA', 'garis' => '#7D8B87'], ['label' => 'Realisasi', 'isi' => '#17735B', 'garis' => '#17735B']];
        $bar = fn ($l, $n) => ['label' => $l, 'nilai' => $n];

        $kosong = $this->render('berpasangan', [], $rr); // no categories at all
        $this->assertStringContainsString('grafik-y', $kosong);

        $nol = $this->render('bertumpuk', [$bar('A', [0, 0])], $rr); // all zero: axis still drawn, no bars
        $this->assertSame(5, substr_count($nol, 'class="grafik-y"'));
        $this->assertStringNotContainsString('class="grafik-batang"', $nol);
        $this->assertStringContainsString('width: 40%', $nol); // single category: narrow and centred

        $lima = $this->render('berpasangan', array_map(fn ($i) => $bar("Irban $i", [$i + 3, $i]), range(1, 5)), $rr);
        $this->assertStringContainsString('width: 100%', $lima);
        $this->assertSame(10, substr_count($lima, 'class="grafik-batang"'));
        $this->assertSame(5, substr_count($lima, '>Irban '));

        $this->render('persen', [$bar('Reguler', [50])], [$rr[1]]); // horizontal chart still renders
    }

    public function test_pdf_text_colours_come_from_the_palette_constants(): void
    {
        $html = $this->html(['rr']);

        $this->assertStringContainsString('color: '.PaletGrafik::TEKS.';', $html);
        $this->assertStringContainsString('color: '.PaletGrafik::TEKS_SEKUNDER.';', $html);
        $this->assertStringNotContainsString(PaletGrafik::TEKS, file_get_contents(resource_path('views/pdf/rekap-triwulan.blade.php')));
    }
}
