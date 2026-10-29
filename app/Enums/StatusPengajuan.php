<?php

namespace App\Enums;

/** Stored workflow state of an SPT (`spt.status_pengajuan`); the displayed status is `Spt::status()`. */
enum StatusPengajuan: string
{
    case Draf = 'draf';
    case MenungguNomor = 'menunggu_nomor';
    case PerluRevisi = 'perlu_revisi';
    case Ditolak = 'ditolak';
    case Diterima = 'diterima';
    case Dibatalkan = 'dibatalkan';

    /** Stored-state label (Register "Status Pengajuan"); every case but Diterima shares its StatusSpt label. */
    public function label(): string
    {
        return $this === self::Diterima ? 'Diterima' : StatusSpt::from($this->value)->label();
    }
}
