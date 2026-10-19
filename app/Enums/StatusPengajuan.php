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
}
