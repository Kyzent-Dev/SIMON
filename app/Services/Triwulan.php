<?php

namespace App\Services;

use App\Models\PkptItem;
use App\Models\Spt;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Quarter of an SPT or PKPT item (PRD §9.5); basis date from `rekap.dasar_triwulan`, read once per instance. */
class Triwulan
{
    private string $dasar;

    public function __construct(Settings $settings, private KesesuaianPkpt $kesesuaian)
    {
        $this->dasar = $settings->get('rekap.dasar_triwulan');
    }

    /** tanggal_selesai / tanggal_mulai / tanggal_lhp per the setting; tanggal_lhp falls back to tanggal_selesai. */
    public function tanggalDasar(Spt $spt): ?CarbonImmutable
    {
        return match ($this->dasar) {
            'tanggal_mulai' => $spt->tanggal_mulai,
            'tanggal_lhp' => $spt->tanggal_lhp ?? $spt->tanggal_selesai,
            default => $spt->tanggal_selesai,
        };
    }

    /** Item range (KesesuaianPkpt): its start date when the setting is tanggal_mulai, else its end date. */
    public function tanggalDasarItem(PkptItem $item): CarbonImmutable
    {
        [$mulai, $selesai] = $this->kesesuaian->rentang($item);

        return $this->dasar === 'tanggal_mulai' ? $mulai : $selesai;
    }

    public function dari(DateTimeInterface $tanggal): int
    {
        return intdiv((int) $tanggal->format('n') - 1, 3) + 1;
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day of the quarter */
    public function rentang(int $tahun, int $tw): array
    {
        $awal = CarbonImmutable::create($tahun, ($tw - 1) * 3 + 1, 1);

        return [$awal, $awal->addMonths(2)->endOfMonth()->startOfDay()];
    }

    /** SQL twin of tanggalDasar(), qualified with `spt.` so it is safe in joins. Keep both in step. */
    public function kolomDasar(): string
    {
        return match ($this->dasar) {
            'tanggal_mulai' => 'spt.tanggal_mulai',
            'tanggal_lhp' => 'coalesce(spt.tanggal_lhp, spt.tanggal_selesai)',
            default => 'spt.tanggal_selesai',
        };
    }

    /** SPT whose basis date falls in quarter $tw of any year (the "Terlambat (semua tahun)" view of Monitoring). */
    public function batasiTriwulan(EloquentBuilder|Builder $q, int $tw): void
    {
        $kolom = DB::raw($this->kolomDasar());
        $q->whereMonth($kolom, '>=', ($tw - 1) * 3 + 1)->whereMonth($kolom, '<=', $tw * 3);
    }

    /** SPT whose basis date (kolomDasar) falls in year $tahun (or its quarter $tw), and in month $bulan when given. */
    public function batasi(EloquentBuilder|Builder $q, int $tahun, ?int $tw = null, ?int $bulan = null): void
    {
        $kolom = DB::raw($this->kolomDasar());
        $antara = fn (CarbonImmutable $awal, CarbonImmutable $akhir) => $q->whereDate($kolom, '>=', $awal->toDateString())->whereDate($kolom, '<=', $akhir->toDateString());

        $antara(...($tw === null ? [CarbonImmutable::create($tahun, 1, 1), CarbonImmutable::create($tahun, 12, 31)] : $this->rentang($tahun, $tw)));
        if ($bulan !== null) {
            $awal = CarbonImmutable::create($tahun, $bulan, 1);
            $antara($awal, $awal->endOfMonth()->startOfDay());
        }
    }
}
