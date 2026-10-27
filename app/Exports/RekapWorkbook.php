<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/** Rekap Triwulanan workbook: one RekapSheet per sheet, built by PembangunRekap::export(). */
class RekapWorkbook implements Export, WithMultipleSheets
{
    /** @param  list<RekapSheet>  $sheets */
    public function __construct(private array $sheets)
    {
        $dipakai = [];
        foreach ($sheets as $s) {
            $s->unikkan($dipakai);
        }
    }

    public function sheets(): array
    {
        return $this->sheets;
    }
}
