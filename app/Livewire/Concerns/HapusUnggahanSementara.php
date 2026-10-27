<?php

namespace App\Livewire\Concerns;

use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait HapusUnggahanSementara
{
    /** TemporaryUploadedFile::delete() leaves its `.json` metadata sidecar behind; remove both. */
    private function hapusUnggahan(TemporaryUploadedFile $file): void
    {
        $sidecar = $file->getRealPath().'.json';
        $file->delete();
        if (is_file($sidecar)) {
            unlink($sidecar);
        }
    }
}
