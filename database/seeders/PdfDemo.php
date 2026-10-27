<?php

namespace Database\Seeders;

use Barryvdh\DomPDF\Facade\Pdf;

/** A small valid PDF (with xref) for the demo documents; built once with dompdf. */
class PdfDemo
{
    private static ?string $isi = null;

    public static function isi(): string
    {
        return self::$isi ??= Pdf::loadHTML('<p>Dokumen contoh SINTAS.</p>')->output();
    }
}
