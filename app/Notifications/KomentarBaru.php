<?php

namespace App\Notifications;

use App\Models\Komentar;

/** A new root comment (or Minta Penjelasan), or a reply that is not the peminta's answer. */
class KomentarBaru extends NotifikasiKomentar
{
    private bool $minta;

    private bool $balasan;

    public function __construct(Komentar $komentar)
    {
        parent::__construct($komentar);
        $this->balasan = $komentar->parent_id !== null;
        $this->minta = ! $this->balasan && $komentar->minta_penjelasan;
    }

    public function jenis(): string
    {
        return $this->minta ? 'minta_penjelasan' : 'komentar_baru';
    }

    public function judul(): string
    {
        return match (true) {
            $this->minta => "{$this->penulis} meminta penjelasan",
            $this->balasan => "Balasan komentar dari {$this->penulis}",
            default => "Komentar baru dari {$this->penulis}",
        };
    }
}
