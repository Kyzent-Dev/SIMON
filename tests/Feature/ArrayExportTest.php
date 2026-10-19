<?php

namespace Tests\Feature;

use App\Exports\ArrayExport;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ArrayExportTest extends TestCase
{
    public function test_formula_like_strings_are_stored_as_text_and_numbers_stay_numbers(): void
    {
        $nilai = ['=HYPERLINK("http://x","klik")', '+1+1', '-2+3', '@SUM(A1)', "\t=1"];
        Excel::store(new ArrayExport(['Kolom'], array_map(fn ($v) => [$v, 42], $nilai)), 'uji-array-export.xlsx', 'local');
        $path = storage_path('app/private/uji-array-export.xlsx');
        $path = is_file($path) ? $path : storage_path('app/uji-array-export.xlsx');
        $sheet = IOFactory::load($path)->getActiveSheet();
        @unlink($path);

        foreach ($nilai as $i => $v) {
            $sel = $sheet->getCell('A'.($i + 2));
            $this->assertSame(DataType::TYPE_STRING, $sel->getDataType(), $v);
            $this->assertSame($v, $sel->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('B'.($i + 2))->getDataType());
        }
    }
}
