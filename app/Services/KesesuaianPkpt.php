<?php

namespace App\Services;

use App\Models\PkptItem;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class KesesuaianPkpt
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} latest approved revision's dates, else the item's own. */
    public function rentang(PkptItem $item): array
    {
        $src = $item->revisiDisetujuiTerakhir() ?? $item;

        return [CarbonImmutable::parse($src->tanggal_mulai)->startOfDay(), CarbonImmutable::parse($src->tanggal_selesai)->startOfDay()];
    }

    public function sesuai(PkptItem $item, DateTimeInterface|string $mulai, DateTimeInterface|string $selesai): bool
    {
        [$m, $s] = $this->rentang($item);

        return $m->lte(CarbonImmutable::parse($mulai)->startOfDay()) && CarbonImmutable::parse($selesai)->startOfDay()->lte($s);
    }
}
