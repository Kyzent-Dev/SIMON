<?php

namespace App\Support;

use App\Models\Spt;
use App\Models\SptDokumen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** PDF documents of an SPT on the private `local` disk, path `spt/{id}/{uuid}.pdf`, never overwritten. */
class BerkasSpt
{
    public static function pdf(UploadedFile $f, int $maksBytes): bool
    {
        return strtolower($f->getClientOriginalExtension()) === 'pdf' && $f->getMimeType() === 'application/pdf' && $f->getSize() <= $maksBytes;
    }

    /** Writes the file and adds a spt_dokumen row. If the insert fails the file is deleted here; if the caller's transaction fails later, it deletes `->path`. */
    public static function simpan(Spt $spt, UploadedFile $f, string $jenis, User $oleh, ?int $diinputOleh): SptDokumen
    {
        $path = $f->storeAs("spt/{$spt->id}", Str::uuid().'.pdf', 'local');

        try {
            return $spt->dokumen()->create([
                'jenis' => $jenis, 'path' => $path, 'nama_asli' => $f->getClientOriginalName(), 'ukuran' => $f->getSize(),
                'sha256' => hash_file('sha256', $f->getRealPath()), 'diunggah_oleh' => $oleh->id, 'diinput_oleh' => $diinputOleh,
            ]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }
}
