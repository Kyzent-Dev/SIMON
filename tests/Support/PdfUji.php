<?php

namespace Tests\Support;

use Illuminate\Http\UploadedFile;

/** A fake upload that is a real PDF to BerkasSpt::pdf(): reports application/pdf and $kb KB, and the file starts with `%PDF-` (`UploadedFile::fake()->create()` fills zeros). */
class PdfUji
{
    public static function buat(string $nama = 'x.pdf', int $kb = 10): UploadedFile
    {
        $f = UploadedFile::fake()->create($nama, $kb, 'application/pdf');
        file_put_contents($f->getPathname(), "%PDF-1.4\n%%EOF\n");

        return $f;
    }
}
