<?php

namespace App\Notifications;

/** Reply in a Minta Penjelasan thread, sent to the pimpinan who asked. */
class BalasanPenjelasan extends NotifikasiKomentar
{
    public function jenis(): string
    {
        return 'balasan_penjelasan';
    }

    public function judul(): string
    {
        return "Balasan atas permintaan penjelasan dari {$this->penulis}";
    }
}
