<?php

namespace App\Exports;

use App\Exports\Concerns\AmanFormula;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet of the Rekap workbook (spec 9.2 §2.3): rows 1–3 title, sheet name and "Dibuat", row 4 empty (frozen above),
 * then per section a title row, a coloured header row and the data rows, one empty row between sections.
 * Column widths come from the column types (LEBAR), the widest over all sections of the sheet; no auto-size.
 */
class RekapSheet extends DefaultValueBinder implements FromArray, WithColumnWidths, WithCustomValueBinder, WithEvents, WithStrictNullComparison, WithStyles, WithTitle
{
    use AmanFormula;

    /** mint-700 (DESIGN §13). Second place a design hex lives in code, after resources/js/grafik.js. */
    public const HEADER_ISI = '17735B';

    /** neutral-300 (DESIGN §13). */
    public const GARIS = 'B8C4C0';

    public const LEBAR = ['label' => 45, 'teks' => 22, 'nomor' => 34, 'tanggal' => 26, 'angka' => 12, 'panjang' => 60];

    private const BUNGKUS = ['label', 'teks', 'tanggal', 'panjang'];

    /** @var list<list<int|string>> */
    private array $baris;

    /** Row numbers per section. @var list<array{judul: int, kepala: int, akhir: int, kolom: string, tipe: list<string>, total: list<int>, gabung: list<array{0: int, 1: int}>}> */
    private array $tata = [];

    /**
     * @param  string  $dibuat  e.g. "24 Oktober 2026 09.15 WIB"
     * @param  list<array{judul: string, kolom: list<string>, tipe: list<string>, baris: list<list<int|string>>, total: list<int>, gabung?: list<array{0: int, 1: int}>}>  $bagian  total/gabung: indexes into baris
     */
    public function __construct(string $judul, private string $nama, string $dibuat, array $bagian)
    {
        $this->baris = [[$judul], [$nama], ['Dibuat: '.$dibuat], [null]];
        foreach ($bagian as $i => $b) {
            if ($i > 0) {
                $this->baris[] = [null]; // an empty array would be dropped by Laravel Excel
            }
            $r = count($this->baris) + 1;
            $this->baris[] = [$b['judul']];
            $this->baris[] = $b['kolom'];
            array_push($this->baris, ...$b['baris']);
            $data = $r + 2;
            $this->tata[] = [
                'judul' => $r, 'kepala' => $r + 1, 'akhir' => $r + 1 + count($b['baris']),
                'kolom' => Coordinate::stringFromColumnIndex(count($b['kolom'])), 'tipe' => $b['tipe'],
                'total' => array_map(fn ($t) => $data + $t, $b['total']),
                'gabung' => array_map(fn ($g) => [$data + $g[0], $data + $g[1]], $b['gabung'] ?? []),
            ];
        }
    }

    public function array(): array
    {
        return $this->baris;
    }

    /** Excel sheet names: at most 31 characters, none of `: \ / ? * [ ]`. */
    public function title(): string
    {
        return mb_substr(str_replace([':', '\\', '/', '?', '*', '[', ']'], '', $this->nama), 0, 31);
    }

    public function columnWidths(): array
    {
        $lebar = [];
        foreach ($this->tata as $t) {
            foreach ($t['tipe'] as $i => $tipe) {
                $k = Coordinate::stringFromColumnIndex($i + 1);
                $lebar[$k] = max($lebar[$k] ?? 0, self::LEBAR[$tipe]);
            }
        }

        return $lebar;
    }

    public function styles(Worksheet $sheet): array
    {
        $gaya = [1 => ['font' => ['bold' => true, 'size' => 14]], 2 => ['font' => ['bold' => true]]];
        foreach ($this->tata as $t) {
            $gaya[$t['judul']] = ['font' => ['bold' => true, 'size' => 12]];
            $gaya[$t['kepala']] = ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']]];
            foreach ($t['total'] as $r) {
                $gaya[$r] = ['font' => ['bold' => true]];
            }
        }

        return $gaya;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $s = $e->sheet->getDelegate();
            foreach ($this->tata as $t) {
                $s->getStyle("A{$t['kepala']}:{$t['kolom']}{$t['akhir']}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::GARIS);
                $kepala = $s->getStyle("A{$t['kepala']}:{$t['kolom']}{$t['kepala']}");
                $kepala->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::HEADER_ISI);
                $kepala->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
                foreach ($t['tipe'] as $i => $tipe) {
                    $k = Coordinate::stringFromColumnIndex($i + 1);
                    $sel = $s->getStyle($k.($t['kepala'] + 1).':'.$k.$t['akhir'])->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(in_array($tipe, self::BUNGKUS, true));
                    if ($tipe === 'angka') {
                        $sel->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }
                }
                foreach ($t['total'] as $r) {
                    $s->getStyle("A{$r}:{$t['kolom']}{$r}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::GARIS);
                }
                foreach ($t['gabung'] as [$a, $z]) {
                    if ($z > $a) {
                        $s->mergeCells("A{$a}:A{$z}");
                        $s->mergeCells("B{$a}:B{$z}");
                    }
                }
            }
            $s->freezePane('A4');
            $s->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
        }];
    }
}
