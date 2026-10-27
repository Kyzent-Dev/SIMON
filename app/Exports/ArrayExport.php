<?php

namespace App\Exports;

use App\Exports\Concerns\AmanFormula;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Headings + rows; $textColumns (e.g. ['B']) are formatted as text so long numbers like NIP survive. Strings that could be read as formulas are always stored as text.
 * Strict null comparison: a 0 is written as 0, not as an empty cell (rekap counts).
 */
class ArrayExport extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithEvents, WithHeadings, WithStrictNullComparison
{
    use AmanFormula;

    public function __construct(private array $headings, private array $rows, private array $textColumns = []) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    /** The heading row repeats on every printed page. */
    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $e) => $e->sheet->getDelegate()->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 1)];
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->textColumns, NumberFormat::FORMAT_TEXT);
    }
}
