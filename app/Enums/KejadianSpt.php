<?php

namespace App\Enums;

/** `spt_riwayat.kejadian` values. Plain string constants (not an enum cast) so later phases can add events freely. */
final class KejadianSpt
{
    public const DIBUAT = 'dibuat';

    public const DIUBAH = 'diubah';

    public const DIAJUKAN = 'diajukan';

    public const PERLU_REVISI = 'perlu_revisi';

    public const DITOLAK = 'ditolak';

    public const DITERIMA = 'diterima';

    public const DICATAT_MANUAL = 'dicatat_manual';

    public const DIBATALKAN = 'dibatalkan';

    public const NOMOR_AWAL = 'nomor_awal';
}
