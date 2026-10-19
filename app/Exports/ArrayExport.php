<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/** Headings + rows; $textColumns (e.g. ['B']) are formatted as text so long numbers like NIP survive. Strings that could be read as formulas are always stored as text. */
class ArrayExport extends DefaultValueBinder implements FromArray, WithColumnFormatting, WithCustomValueBinder, WithHeadings
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

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/', $value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function columnFormats(): array
    {
        return array_fill_keys($this->textColumns, NumberFormat::FORMAT_TEXT);
    }
}
