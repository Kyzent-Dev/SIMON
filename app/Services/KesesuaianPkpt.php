<?php

namespace App\Services;

use App\Models\PkptItem;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class KesesuaianPkpt
{
    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} the item's own dates (an approved Perubahan PKPT writes them). */
    public function rentang(PkptItem $item): array
    {
        return [CarbonImmutable::parse($item->tanggal_mulai)->startOfDay(), CarbonImmutable::parse($item->tanggal_selesai)->startOfDay()];
    }

    public function sesuai(PkptItem $item, DateTimeInterface|string $mulai, DateTimeInterface|string $selesai): bool
    {
        return $this->sesuaiRentang($this->rentang($item), $mulai, $selesai);
    }

    /** @param array{0: DateTimeInterface|string, 1: DateTimeInterface|string} $rentang PKPT range; also for a range that is not saved yet (Perubahan PKPT impact). */
    public function sesuaiRentang(array $rentang, DateTimeInterface|string $mulai, DateTimeInterface|string $selesai): bool
    {
        [$m, $s] = [CarbonImmutable::parse($rentang[0])->startOfDay(), CarbonImmutable::parse($rentang[1])->startOfDay()];

        return $m->lte(CarbonImmutable::parse($mulai)->startOfDay()) && CarbonImmutable::parse($selesai)->startOfDay()->lte($s);
    }
}
