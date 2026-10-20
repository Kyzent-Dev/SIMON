<?php

namespace Tests\Feature\Publik;

use App\Models\IrbanWilayah;
use App\Models\Objek;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Services\Publik\JadwalPublik;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalPublikTest extends TestCase
{
    use PublikFixture;
    use RefreshDatabase;

    private function svc(): JadwalPublik
    {
        return app(JadwalPublik::class);
    }

    /** Minimal item array as item() returns it (only the keys linimasa/bulanan read). */
    private function it(int $id, string $mulai, string $selesai, int $jenis = 1, ?array $semula = null): array
    {
        return ['id' => $id, 'jenis' => ['id' => $jenis, 'nama' => "J$jenis", 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF'],
            'mulai' => CarbonImmutable::parse($mulai), 'selesai' => CarbonImmutable::parse($selesai),
            'semula' => $semula ? array_map(fn ($d) => CarbonImmutable::parse($d), $semula) : null];
    }

    public function test_items_of_the_disahkan_pkpt_with_effective_range_and_wilayah(): void
    {
        $tanpaSpt = PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->c->id, 'jenis_penugasan_id' => $this->j1->id,
            'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-31', 'target_spt' => 4]);
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $tanpaSpt->id, 'tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-28']);
        PkptItemRevisi::factory()->create(['pkpt_item_id' => $this->a1->id, 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30']); // pending
        PkptItemRevisi::factory()->ditolak()->create(['pkpt_item_id' => $this->b1->id, 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30']);

        $item = $this->svc()->item(2026, null);
        // Ordered by jenis name (Dua before Satu), then effective start: b1; a1 (Jan), tanpaSpt (Aug). d1 (Draf PKPT) absent.
        $this->assertSame([$this->b1->id, $this->a1->id, $tanpaSpt->id], array_column($item, 'id'));
        $this->assertSame(['id', 'jenis', 'irban', 'mulai', 'selesai', 'semula', 'wilayah', 'selesaiSpt', 'target'], array_keys($item[0]));

        [$b1, $a1, $t] = $item;
        $this->assertSame(['Irban B', '2026-04-01', '2026-06-30', null, 1], [$b1['irban'], $b1['mulai']->toDateString(), $b1['selesai']->toDateString(), $b1['semula'], $b1['target']]);
        $this->assertSame(['2026-01-05', '2026-03-31', null, 2], [$a1['mulai']->toDateString(), $a1['selesai']->toDateString(), $a1['semula'], $a1['target']]);
        $this->assertSame(['2026-08-03', '2026-08-28'], [$t['mulai']->toDateString(), $t['selesai']->toDateString()]);
        $this->assertSame(['2026-07-01', '2026-07-31'], array_map(fn ($d) => $d->toDateString(), $t['semula']));
        $this->assertSame(['id' => $this->j1->id, 'nama' => 'Jenis Satu', 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF'], $a1['jenis']);

        // a1: diterima SPT v1 (Desa X1 → Kec X), e2 (Kec Y); e4 is Dibatalkan, so not counted. b1: v2 (Kec Y, Dinas D).
        $this->assertSame(['Kec X, Kec Y', 'Dinas D, Kec Y', 'Belum ditentukan'], [$a1['wilayah'], $b1['wilayah'], $t['wilayah']]);
        $this->assertSame([1, 1, 0], [$a1['selesaiSpt'], $b1['selesaiSpt'], $t['selesaiSpt']]);

        $this->assertSame([$this->a1->id], array_column($this->svc()->item(2026, $this->a->id), 'id'));
        $this->assertSame([], $this->svc()->item(2027, null)); // Draf
        $this->assertSame([], $this->svc()->item(2030, null)); // no PKPT
    }

    public function test_grid_column_mapping_and_year_clamp(): void
    {
        // column = (bulan − 1) × 5 + min(5, ceil(hari / 7)): day 1 → 1, 7 → 1, 8 → 2, 28 → 4, 29 → 5, 31 → 5.
        $kasus = [['2026-01-01', '2026-01-07', 1, 1], ['2026-01-08', '2026-01-28', 2, 4], ['2026-01-29', '2026-01-31', 5, 5],
            ['2026-03-08', '2026-12-31', 12, 60], ['2025-12-20', '2026-01-10', 1, 2], ['2026-12-20', '2027-01-15', 58, 60]];
        foreach ($kasus as $n => [$m, $s, $km, $ks]) {
            $bar = $this->svc()->linimasa([$this->it($n + 1, $m, $s)], 2026)['baris'][0]['lajur'][0][0];
            $this->assertSame([$km, $ks], [$bar['mulai'], $bar['selesai']], "$m – $s");
        }
        $bar = $this->svc()->linimasa([$this->it(1, '2026-03-01', '2026-03-10', 1, ['2026-02-01', '2026-02-14'])], 2026)['baris'][0]['lajur'][0][0];
        $this->assertSame([6, 7], $bar['semula']); // 1 Feb → 6, 14 Feb → 7

        $this->assertSame(58, $this->svc()->linimasa([], 2026)['hariIni']); // 15 Des → 55 + 3
        $this->assertNull($this->svc()->linimasa([], 2025)['hariIni']);
    }

    public function test_lane_packing(): void
    {
        // Rule: an item joins the first lane whose last bar ends in a column strictly before its start column;
        // sharing a column (even "touching" dates in the same week column) counts as overlap.
        $item = [
            $this->it(1, '2026-01-01', '2026-01-07'), // cols 1–1 → lane 1
            $this->it(2, '2026-01-07', '2026-01-14'), // cols 1–2, shares col 1 → lane 2
            $this->it(3, '2026-01-08', '2026-01-20'), // cols 2–3, lane 1 ends at 1 < 2 → lane 1
            $this->it(4, '2026-01-14', '2026-01-31'), // cols 2–5, lane 1 ends 3, lane 2 ends 2 → lane 3
            $this->it(5, '2026-02-01', '2026-02-05', 2), // other jenis → its own row
        ];
        $baris = $this->svc()->linimasa($item, 2026)['baris'];
        $this->assertSame(['J1', 'J2'], array_map(fn ($b) => $b['jenis']['nama'], $baris));
        $this->assertSame([4, 1], array_column($baris, 'jumlah'));
        $lajur = array_map(fn ($l) => array_map(fn ($bar) => $bar['item']['id'], $l), $baris[0]['lajur']);
        $this->assertSame([[1, 3], [2], [4]], $lajur);
    }

    public function test_bulanan_lists_an_item_in_every_month_it_overlaps(): void
    {
        $item = [
            $this->it(1, '2026-01-29', '2026-03-02'), // Jan Minggu 5, Feb + Mar Minggu 1
            $this->it(2, '2025-12-28', '2026-01-05'), // starts last year → Jan Minggu 1
            $this->it(3, '2026-01-08', '2026-01-09'), // Jan Minggu 2
        ];
        $b = $this->svc()->bulanan($item, 2026);
        $this->assertCount(12, $b);
        $this->assertSame(['Januari', 3], [$b[0]['nama'], $b[0]['jumlah']]);
        $ids = fn (array $bulan) => array_map(fn ($m) => array_column($m, 'id'), $bulan['minggu']);
        $this->assertSame([1 => [2], 2 => [3], 5 => [1]], $ids($b[0]));
        $this->assertSame([1 => [1]], $ids($b[1]));
        $this->assertSame([1 => [1]], $ids($b[2]));
        $this->assertSame([0, []], [$b[3]['jumlah'], $b[3]['minggu']]);
        $this->assertSame(range(1, 12), array_column($b, 'bulan'));
    }

    public function test_wilayah_panel_per_irban(): void
    {
        $rsud = Objek::factory()->create(['nama' => 'RSUD R', 'jenis' => 'rsud']);
        $bumd = Objek::factory()->create(['nama' => 'BUMD M', 'jenis' => 'bumd']);
        foreach ([$this->kecY, $this->kecX, $this->dinasD, $rsud, $bumd] as $o) {
            IrbanWilayah::create(['irban_id' => $this->a->id, 'tahun' => 2026, 'objek_id' => $o->id]);
        }
        IrbanWilayah::create(['irban_id' => $this->b->id, 'tahun' => 2025, 'objek_id' => $this->kecX->id]); // other year

        $this->assertSame([
            ['irban' => 'Irban A', 'daftar' => ['Kecamatan' => ['Kec X', 'Kec Y'], 'Dinas/Badan' => ['Dinas D', 'RSUD R'], 'BUMD' => ['BUMD M']]],
            ['irban' => 'Irban B', 'daftar' => ['Kecamatan' => [], 'Dinas/Badan' => [], 'BUMD' => []]],
            ['irban' => 'Irban C', 'daftar' => ['Kecamatan' => [], 'Dinas/Badan' => [], 'BUMD' => []]],
        ], $this->svc()->wilayah(2026));
    }
}
