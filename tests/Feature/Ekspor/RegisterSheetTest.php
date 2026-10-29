<?php

namespace Tests\Feature\Ekspor;

use App\Actions\Spt\TerimaPengajuan;
use App\Enums\Role;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Services\Ekspor\PembangunRegister;
use App\Services\RegisterNomor;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/** Spec 1.1.4 Task 3: the styled Register workbook and the PDF. */
class RegisterSheetTest extends TestCase
{
    use RefreshDatabase;

    private User $bu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Asia/Jakarta'));
        $this->bu = User::factory()->create(['role' => Role::BagianUmum]);
    }

    private function terbit(string $uraian, int $anggota = 0): Spt
    {
        $irban = Irban::first() ?? Irban::factory()->create(['kode_nomor' => 'INSPEKTORAT-II']);
        $spt = app(TerimaPengajuan::class)->handle($this->bu, Spt::factory()->menungguNomor()->create(['irban_id' => $irban->id, 'uraian_untuk' => $uraian]));
        for ($u = 1; $u <= $anggota; $u++) {
            $spt->personel()->create(['pegawai_id' => Pegawai::factory()->create(['nama_bergelar' => "Orang {$u}"])->id, 'peran' => $u === 1 ? 'ketua' : 'anggota', 'urutan' => $u, 'sumber' => 'tambahan']);
        }

        return $spt;
    }

    private function baca(array $filter, ?string $simpanKe = null): Worksheet
    {
        $tmp = tempnam(sys_get_temp_dir(), 'r').'.xlsx';
        file_put_contents($tmp, Excel::raw((new PembangunRegister)->export($this->bu, $filter), \Maatwebsite\Excel\Excel::XLSX));
        $simpanKe && copy($tmp, $simpanKe);
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        return $sheet;
    }

    public function test_content_styles_and_print_setup(): void
    {
        $this->terbit('Satu', 3);
        $this->terbit('=HYPERLINK("x")');
        $f = (new PembangunRegister)->filter($this->bu, ['tahun' => 2026, 'cari' => 'SPT']);
        $s = $this->baca($f, null);

        $this->assertSame('Register Nomor SPT 2026', $s->getCell('A1')->getValue());
        $this->assertSame('Filter: Cari: SPT', $s->getCell('A2')->getValue());
        $this->assertStringContainsString('2 nomor', $s->getCell('A3')->getValue());

        foreach (RegisterNomor::KOLOM as $i => $k) {
            $this->assertSame($k, $s->getCell([$i + 1, 4])->getValue());
        }
        // Body equals RegisterNomor::semua(); only the anggota separator differs ("\n" in the cell instead of "; ").
        $layar = app(RegisterNomor::class)->semua($f);
        foreach (app(RegisterNomor::class)->semua($f, "\n") as $r => $baris) {
            foreach ($baris as $c => $nilai) {
                $this->assertSame($nilai, $s->getCell([$c + 1, 5 + $r])->getValue());
                $this->assertSame(str_replace("\n", '; ', $nilai), $layar[$r][$c]);
            }
        }
        $this->assertSame("Orang 1 (Ketua Tim)\nOrang 2 (Anggota)\nOrang 3 (Anggota)", $s->getCell('M5')->getValue());
        $this->assertTrue($s->getStyle('M5')->getAlignment()->getWrapText());
        $this->assertSame(Alignment::VERTICAL_TOP, $s->getStyle('M5')->getAlignment()->getVertical());

        $this->assertSame('=HYPERLINK("x")', $s->getCell('K6')->getValue());
        $this->assertSame('s', $s->getCell('K6')->getDataType());

        $h = $s->getStyle('A4');
        $this->assertSame('17735B', $h->getFill()->getStartColor()->getRGB());
        $this->assertTrue($h->getFont()->getBold());
        $this->assertSame('FFFFFF', $h->getFont()->getColor()->getRGB());
        $this->assertSame('B8C4C0', $s->getStyle('P6')->getBorders()->getRight()->getColor()->getRGB());
        $this->assertSame('thin', $s->getStyle('A5')->getBorders()->getLeft()->getBorderStyle());
        $this->assertEquals(34, $s->getColumnDimension('A')->getWidth());
        $this->assertEquals(60, $s->getColumnDimension('K')->getWidth());

        $this->assertSame('B5', $s->getFreezePane());
        $this->assertSame('A4:P6', $s->getAutoFilter()->getRange());
        $p = $s->getPageSetup();
        $this->assertSame(PageSetup::ORIENTATION_LANDSCAPE, $p->getOrientation());
        $this->assertSame(PageSetup::PAPERSIZE_A4, $p->getPaperSize());
        $this->assertSame([1, 0, true], [$p->getFitToWidth(), $p->getFitToHeight(), $p->getFitToPage()]);
        $this->assertSame([4, 4], $p->getRowsToRepeatAtTop());
    }

    public function test_empty_register_still_has_header_and_filter(): void
    {
        $s = $this->baca((new PembangunRegister)->filter($this->bu, ['tahun' => 2026]));

        $this->assertSame('Nomor SPT', $s->getCell('A4')->getValue());
        $this->assertNull($s->getCell('A5')->getValue());
        $this->assertSame('A4:P4', $s->getAutoFilter()->getRange());
    }

    public function test_pdf_renders_with_the_16_columns(): void
    {
        $this->terbit('Satu', 2);
        $html = (new PembangunRegister)->html($this->bu, ['tahun' => 2026]);

        foreach (RegisterNomor::KOLOM as $k) {
            $this->assertStringContainsString("<th>{$k}</th>", $html);
        }
        $pdf = app(RegisterNomor::class)->pdf(['tahun' => 2026]);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_pdf_of_200_rows_stays_light(): void
    {
        $irban = Irban::factory()->create(['kode_nomor' => 'INSPEKTORAT-II']);
        Spt::factory()->count(200)->diterima()->create(['irban_id' => $irban->id, 'tahun' => 2026]);
        $pdf = app(RegisterNomor::class)->pdf(['tahun' => 2026]);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertLessThan(5 * 1024 * 1024, strlen($pdf));
    }

    public function test_numeric_looking_nomor_sppd_stays_text(): void
    {
        $a = $this->terbit('Satu');
        $a->forceFill(['nomor_sppd' => '1e5'])->save();
        $b = $this->terbit('Dua');
        $b->forceFill(['nomor_sppd' => '123'])->save();
        $s = $this->baca((new PembangunRegister)->filter($this->bu, ['tahun' => 2026]));

        $hasil = [$s->getCell('C5')->getValue(), $s->getCell('C6')->getValue()];
        sort($hasil);
        $this->assertSame(['123', '1e5'], $hasil);
        $this->assertSame('s', $s->getCell('C5')->getDataType());
        $this->assertSame('s', $s->getCell('C6')->getDataType());
    }
}
