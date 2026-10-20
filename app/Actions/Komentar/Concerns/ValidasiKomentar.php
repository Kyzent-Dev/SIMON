<?php

namespace App\Actions\Komentar\Concerns;

/** Shared `isi` rule of the two write actions. */
trait ValidasiKomentar
{
    /** Trimmed text; a message goes into $errors under `isi`. */
    private function isi(array $data, array &$errors): string
    {
        $isi = trim((string) ($data['isi'] ?? ''));
        if ($isi === '') {
            $errors['isi'] = 'Tulis isi komentar.';
        } elseif (mb_strlen($isi) > 2000) {
            $errors['isi'] = 'Komentar maksimal 2000 karakter.';
        }

        return $isi;
    }
}
