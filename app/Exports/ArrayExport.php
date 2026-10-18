<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/** Headings + rows; $textColumns (e.g. ['B']) are formatted as text so long numbers like NIP survive. */
class ArrayExport implements FromArray, WithColumnFormatting, WithHeadings
{
    public function __construct(private array $headings, private array $rows, private array $textColumns = []) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->textColumns, NumberFormat::FORMAT_TEXT);
    }
}
