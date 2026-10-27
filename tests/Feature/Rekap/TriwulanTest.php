<?php

namespace Tests\Feature\Rekap;

use App\Models\PkptItem;
use App\Models\Spt;
use App\Services\Settings;
use App\Services\Triwulan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\JadwalItem;
use Tests\TestCase;

class TriwulanTest extends TestCase
{
    use RefreshDatabase;

    private function triwulan(string $dasar): Triwulan
    {
        app(Settings::class)->set('rekap.dasar_triwulan', $dasar);

        return app(Triwulan::class);
    }

    public function test_dari_and_rentang_quarter_edges(): void
    {
        $tw = app(Triwulan::class);
        $cases = ['2026-01-01' => 1, '2026-03-31' => 1, '2026-04-01' => 2, '2026-06-30' => 2, '2026-07-01' => 3, '2026-09-30' => 3, '2026-10-01' => 4, '2026-12-31' => 4];
        foreach ($cases as $tanggal => $expected) {
            $this->assertSame($expected, $tw->dari(CarbonImmutable::parse($tanggal)), $tanggal);
        }

        $expected = [1 => ['2026-01-01', '2026-03-31'], 2 => ['2026-04-01', '2026-06-30'], 3 => ['2026-07-01', '2026-09-30'], 4 => ['2026-10-01', '2026-12-31']];
        foreach ($expected as $n => [$awal, $akhir]) {
            [$a, $b] = $tw->rentang(2026, $n);
            $this->assertSame([$awal, $akhir], [$a->toDateString(), $b->toDateString()], "TW $n");
        }
        $this->assertSame('2024-03-31', $tw->rentang(2024, 1)[1]->toDateString());
    }

    public function test_tanggal_dasar_per_setting_with_lhp_fallback(): void
    {
        // mulai 31 Maret (TW I), selesai 1 April (TW II), LHP 1 Juli (TW III).
        $spt = Spt::factory()->diterima()->create(['tanggal_mulai' => '2026-03-31', 'tanggal_selesai' => '2026-04-01']);
        $spt->forceFill(['tanggal_lhp' => '2026-07-01'])->save();
        $spt = $spt->fresh();
        $tanpaLhp = Spt::factory()->diterima()->create(['tanggal_mulai' => '2026-03-31', 'tanggal_selesai' => '2026-04-01'])->fresh();

        $selesai = $this->triwulan('tanggal_selesai');
        $this->assertSame('2026-04-01', $selesai->tanggalDasar($spt)->toDateString());
        $this->assertSame(2, $selesai->dari($selesai->tanggalDasar($spt)));

        $mulai = $this->triwulan('tanggal_mulai');
        $this->assertSame('2026-03-31', $mulai->tanggalDasar($spt)->toDateString());
        $this->assertSame(1, $mulai->dari($mulai->tanggalDasar($spt)));

        $lhp = $this->triwulan('tanggal_lhp');
        $this->assertSame('2026-07-01', $lhp->tanggalDasar($spt)->toDateString());
        $this->assertSame(3, $lhp->dari($lhp->tanggalDasar($spt)));
        $this->assertSame('2026-04-01', $lhp->tanggalDasar($tanpaLhp)->toDateString()); // fallback to tanggal_selesai

        $kosong = Spt::factory()->create(['tanggal_mulai' => null, 'tanggal_selesai' => null])->fresh();
        $this->assertNull($mulai->tanggalDasar($kosong));
        $this->assertNull($selesai->tanggalDasar($kosong));
        $this->assertNull($lhp->tanggalDasar($kosong));
    }

    public function test_item_basis_date_uses_item_dates_and_setting(): void
    {
        $item = PkptItem::factory()->create(['tanggal_mulai' => '2026-03-31', 'tanggal_selesai' => '2026-04-01']);

        $this->assertSame('2026-04-01', $this->triwulan('tanggal_selesai')->tanggalDasarItem($item)->toDateString());
        $this->assertSame('2026-04-01', $this->triwulan('tanggal_lhp')->tanggalDasarItem($item)->toDateString()); // items have no LHP: end date
        $this->assertSame('2026-03-31', $this->triwulan('tanggal_mulai')->tanggalDasarItem($item)->toDateString());

        JadwalItem::geser($item, '2026-06-30', '2026-07-01'); // moved by an approved Perubahan PKPT
        $this->assertSame('2026-06-30', $this->triwulan('tanggal_mulai')->tanggalDasarItem($item->fresh())->toDateString());
        $this->assertSame('2026-07-01', $this->triwulan('tanggal_selesai')->tanggalDasarItem($item->fresh())->toDateString());
    }

    public function test_batasi_is_the_sql_twin_of_tanggal_dasar(): void
    {
        $a = Spt::factory()->diterima()->create(['tanggal_mulai' => '2026-03-31', 'tanggal_selesai' => '2026-04-01']); // lhp null
        $b = Spt::factory()->diterima()->create(['tanggal_mulai' => '2025-12-31', 'tanggal_selesai' => '2026-01-01']);
        $b->forceFill(['tanggal_lhp' => '2026-12-31'])->save();
        $c = Spt::factory()->diterima()->create(['tanggal_mulai' => '2026-12-31', 'tanggal_selesai' => '2027-01-01']);

        $ids = function (string $dasar, int $tahun, ?int $tw = null): array {
            $q = Spt::query()->orderBy('id');
            $this->triwulan($dasar)->batasi($q, $tahun, $tw);

            return $q->pluck('id')->all();
        };

        $this->assertSame([$a->id, $b->id], $ids('tanggal_selesai', 2026));
        $this->assertSame([$b->id], $ids('tanggal_selesai', 2026, 1));
        $this->assertSame([$a->id], $ids('tanggal_selesai', 2026, 2));
        $this->assertSame([$c->id], $ids('tanggal_selesai', 2027, 1));
        $this->assertSame([$a->id, $c->id], $ids('tanggal_mulai', 2026));
        $this->assertSame([$a->id], $ids('tanggal_mulai', 2026, 1));
        $this->assertSame([$c->id], $ids('tanggal_mulai', 2026, 4));
        $this->assertSame([$b->id], $ids('tanggal_mulai', 2025));
        // lhp: a falls back to selesai (1 April, TW II), b uses its LHP (31 Desember, TW IV), c falls back to 2027.
        $this->assertSame([$a->id, $b->id], $ids('tanggal_lhp', 2026));
        $this->assertSame([$a->id], $ids('tanggal_lhp', 2026, 2));
        $this->assertSame([$b->id], $ids('tanggal_lhp', 2026, 4));
        $this->assertSame([], $ids('tanggal_lhp', 2026, 1));
    }

    public function test_batasi_on_a_joined_query_builder_and_by_month(): void
    {
        // The joined pkpt_item has its own tanggal_* columns (2027): batasi must use the qualified spt basis column.
        $item = PkptItem::factory()->create(['tanggal_mulai' => '2027-01-01', 'tanggal_selesai' => '2027-01-31']);
        $spt = Spt::factory()->diterima()->create(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']);
        foreach (['tanggal_selesai', 'tanggal_mulai', 'tanggal_lhp'] as $dasar) {
            $ids = function (?int $tw, ?int $bulan) use ($dasar) {
                $q = DB::table('spt')->join('pkpt_item', 'pkpt_item.id', '=', 'spt.pkpt_item_id');
                $this->triwulan($dasar)->batasi($q, 2026, $tw, $bulan);

                return $q->pluck('spt.id')->all();
            };
            $this->assertSame([$spt->id], $ids(1, 3), $dasar);
            $this->assertSame([$spt->id], $ids(null, 3), $dasar);
            $this->assertSame([], $ids(null, 2), $dasar);
        }
    }
}
