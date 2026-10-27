<?php

namespace Tests\Feature\Rekap;

use App\Enums\StatusSpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Komentar;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\SptDokumen;
use App\Models\SptKendala;
use App\Models\User;
use App\Services\MonitoringSpt;
use App\Services\PenandaSpt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\JadwalItem;
use Tests\TestCase;

/**
 * Fixture (today 15 Desember 2026, TW by tanggal_selesai, batas terlambat 14 hari):
 *   i1 Irban A, J1, 1–31 Mar            (effective start 1 Mar)
 *   i3 Irban A, J2, 1–30 Apr            (effective start 1 Apr)
 *   i2 Irban B, J2, disahkan 1–28 Feb, moved by an approved Perubahan to 1–31 Mei (start 1 Mei → after i3)
 *   m1 A i1 J1  5–20 Mar   Selesai, final           → sesuai, TW I
 *   m2 A i1 J1 25 Mar–5 Apr berjalan, kendala       → tidak sesuai, Penyusunan Laporan + terlambat, TW II
 *   m3 B i2 J2 10–20 Mei   Selesai, final           → sesuai via the moved dates, TW II
 *   m4 A i3 J2  2–10 Apr   Selesai, final           → sesuai, TW II
 *   m5 B -  J1 10–20 Des   berjalan, open Minta Penjelasan → non-PKPT, Pelaksanaan, TW IV
 *   m6 A -  J2 20–30 Des   berjalan                 → non-PKPT, Siap Dilaksanakan, TW IV
 *   m7 A i1 Dibatalkan, m8 A i1 Menunggu Nomor      → excluded (not diterima)
 *   m9 A i1 spt.tahun 2025, Selesai                  → only with tahun=2025
 */
class MonitoringSptTest extends TestCase
{
    use RefreshDatabase;

    private array $m = [];

    private array $i = [];

    private Irban $a;

    private Irban $b;

    private JenisPenugasan $j1;

    private JenisPenugasan $j2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00', 'Asia/Jakarta'));
        $this->a = Irban::factory()->create(['nama' => 'Irban A']);
        $this->b = Irban::factory()->create(['nama' => 'Irban B']);
        $this->j1 = JenisPenugasan::factory()->create(['nama' => 'Jenis Satu']);
        $this->j2 = JenisPenugasan::factory()->create(['nama' => 'Jenis Dua']);

        $item = fn (Irban $ir, JenisPenugasan $j, string $m, string $s) => PkptItem::factory()->create(
            ['irban_id' => $ir->id, 'jenis_penugasan_id' => $j->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s]);
        $this->i[1] = $item($this->a, $this->j1, '2026-03-01', '2026-03-31');
        $this->i[2] = $item($this->b, $this->j2, '2026-02-01', '2026-02-28');
        $this->i[3] = $item($this->a, $this->j2, '2026-04-01', '2026-04-30');
        JadwalItem::geser($this->i[2], '2026-05-01', '2026-05-31');

        $spt = function (string $state, Irban $ir, ?int $tw, JenisPenugasan $j, string $m, string $s, int $tahun = 2026) {
            $spt = Spt::factory()->{$state}()->create(['irban_id' => $ir->id, 'pkpt_item_id' => $tw ? $this->i[$tw]->id : null,
                'jenis_penugasan_id' => $j->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s]);
            $spt->forceFill(['tahun' => $tahun])->save();

            return $spt;
        };
        $this->m[1] = $spt('selesai', $this->a, 1, $this->j1, '2026-03-05', '2026-03-20');
        $this->m[2] = $spt('diterima', $this->a, 1, $this->j1, '2026-03-25', '2026-04-05');
        $this->m[3] = $spt('selesai', $this->b, 2, $this->j2, '2026-05-10', '2026-05-20');
        $this->m[4] = $spt('selesai', $this->a, 3, $this->j2, '2026-04-02', '2026-04-10');
        $this->m[5] = $spt('diterima', $this->b, null, $this->j1, '2026-12-10', '2026-12-20');
        $this->m[6] = $spt('diterima', $this->a, null, $this->j2, '2026-12-20', '2026-12-30');
        $this->m[7] = $spt('dibatalkan', $this->a, 1, $this->j1, '2026-03-05', '2026-03-06');
        $this->m[8] = $spt('menungguNomor', $this->a, 1, $this->j1, '2026-03-05', '2026-03-06');
        $this->m[9] = $spt('selesai', $this->a, 1, $this->j1, '2025-03-05', '2025-03-20', 2025);
        foreach ([1, 3, 4, 5, 6, 9] as $n) {
            SptDokumen::create(['spt_id' => $this->m[$n]->id, 'jenis' => 'final', 'path' => "spt/{$n}/x.pdf", 'nama_asli' => 'x.pdf',
                'ukuran' => 10, 'sha256' => str_repeat('a', 64), 'diunggah_oleh' => User::factory()->create()->id]);
        }
        SptKendala::factory()->create(['spt_id' => $this->m[2]->id, 'kategori' => 'Objek tidak siap', 'uraian' => 'Objek sedang diaudit BPK.']);
        Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $this->m[5]->id]);
        Komentar::factory()->create(['spt_id' => $this->m[6]->id]); // plain comment, not a permintaan
    }

    /** Group labels and SPT ids per group, e.g. ['i1' => [m1, m2], 'luar' => [...]]. */
    private function ringkas(array $filter = []): array
    {
        $out = [];
        foreach (app(MonitoringSpt::class)->baris($filter) as $g) {
            $key = $g['item_id'] === null ? 'luar' : 'i'.array_search($g['item_id'], array_map(fn ($i) => $i->id, $this->i), true);
            $out[$key] = array_map(fn ($r) => array_search($r['id'], array_map(fn ($s) => $s->id, $this->m), true), $g['spt']);
        }

        return $out;
    }

    public function test_grouping_order_and_di_luar_pkpt_last(): void
    {
        $this->assertSame(['i1' => [1, 2], 'i3' => [4], 'i2' => [3], 'luar' => [5, 6]], $this->ringkas());
        $this->assertSame(['i1' => [9]], $this->ringkas(['tahun' => 2025]));
    }

    public function test_group_and_row_content(): void
    {
        $groups = app(MonitoringSpt::class)->baris([]);

        $i2 = $groups[2];
        $this->assertSame($this->i[2]->id, $i2['item_id']);
        $this->assertSame(['id' => $this->j2->id, 'nama' => 'Jenis Dua', 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF'], $i2['jenis']);
        $this->assertSame(['2026-05-01', '2026-05-31'], [$i2['mulai']->toDateString(), $i2['selesai']->toDateString()]); // item dates (moved)
        $this->assertSame('Irban B', $i2['irban']);

        $luar = $groups[3];
        $this->assertNull($luar['item_id']);
        $this->assertSame('Di luar PKPT', $luar['label']);
        $this->assertNull($luar['jenis']);

        $m2 = $groups[0]['spt'][1];
        $this->assertSame($this->m[2]->id, $m2['id']);
        $this->assertSame($this->m[2]->nomor_lengkap, $m2['nomor_lengkap']);
        $this->assertSame('Irban A', $m2['irban']);
        $this->assertSame('Jenis Satu', $m2['jenis']['nama']);
        $this->assertSame(['2026-03-25', '2026-04-05'], [$m2['mulai']->toDateString(), $m2['selesai']->toDateString()]);
        $this->assertSame('tidak_sesuai', $m2['kesesuaian']);
        $this->assertSame(StatusSpt::PenyusunanLaporan, $m2['status']);
        $this->assertSame(['tidak_sesuai_pkpt', 'terlambat', 'spt_final_belum_diupload'], $m2['penanda']);
        $this->assertSame(['kategori' => 'Objek tidak siap', 'uraian' => 'Objek sedang diaudit BPK.'], $m2['kendala']);
        $this->assertSame(route('spt.show', $this->m[2]->id), $m2['url']);

        $this->assertSame('sesuai', $groups[0]['spt'][0]['kesesuaian']);
        $this->assertNull($groups[0]['spt'][0]['kendala']);
        $this->assertSame(StatusSpt::Selesai, $groups[0]['spt'][0]['status']);
        $this->assertSame('sesuai', $groups[2]['spt'][0]['kesesuaian']); // m3 inside the moved dates
        $this->assertSame('non_pkpt', $luar['spt'][0]['kesesuaian']);
        $this->assertSame(StatusSpt::Pelaksanaan, $luar['spt'][0]['status']);
        $this->assertSame(StatusSpt::SiapDilaksanakan, $luar['spt'][1]['status']);
    }

    public function test_each_filter_alone(): void
    {
        $cases = [
            [['tw' => 1], ['i1' => [1]]],
            [['tw' => 2], ['i1' => [2], 'i3' => [4], 'i2' => [3]]],
            [['tw' => 3], []],
            [['tw' => 4], ['luar' => [5, 6]]],
            [['irban' => 'B'], ['i2' => [3], 'luar' => [5]]],
            [['status' => 'berjalan'], ['i1' => [2], 'luar' => [5, 6]]],
            [['status' => 'selesai'], ['i1' => [1], 'i3' => [4], 'i2' => [3]]],
            [['status' => 'pelaksanaan'], ['luar' => [5]]],
            [['status' => 'siap_dilaksanakan'], ['luar' => [6]]],
            [['status' => 'penyusunan_laporan'], ['i1' => [2]]],
            [['jenis' => 'J1'], ['i1' => [1, 2], 'luar' => [5]]],
            [['kesesuaian' => 'sesuai'], ['i1' => [1], 'i3' => [4], 'i2' => [3]]],
            [['kesesuaian' => 'tidak_sesuai'], ['i1' => [2]]],
            [['kesesuaian' => 'non_pkpt'], ['luar' => [5, 6]]],
            [['penanda' => 'terlambat'], ['i1' => [2]]],
            [['penjelasan' => 'terbuka'], ['luar' => [5]]],
        ];
        foreach ($cases as [$filter, $expected]) {
            $this->assertSame($expected, $this->ringkas($this->ids($filter)), json_encode($filter));
        }
    }

    public function test_combined_filters(): void
    {
        $this->assertSame(['i1' => [2], 'luar' => [6]], $this->ringkas($this->ids(['irban' => 'A', 'status' => 'berjalan'])));
        $this->assertSame(['i3' => [4], 'i2' => [3]], $this->ringkas(['tw' => 2, 'kesesuaian' => 'sesuai']));
        $this->assertSame(['luar' => [6]], $this->ringkas($this->ids(['irban' => 'A', 'tw' => 4, 'status' => 'siap_dilaksanakan'])));
        $this->assertSame([], $this->ringkas($this->ids(['irban' => 'B', 'penanda' => 'terlambat'])));
        $this->assertSame(['i1' => [2]], $this->ringkas($this->ids(['jenis' => 'J1', 'kesesuaian' => 'tidak_sesuai', 'tw' => 2])));
    }

    /** "Terlambat (semua tahun)" with a quarter: the quarter of every year, not of the selected year only. */
    public function test_tw_with_the_terlambat_chip_covers_all_years(): void
    {
        $lama = Spt::factory()->diterima()->create(['irban_id' => $this->a->id, 'pkpt_item_id' => null, 'jenis_penugasan_id' => $this->j1->id,
            'tanggal_mulai' => '2025-05-01', 'tanggal_selesai' => '2025-05-10']);
        $lama->forceFill(['tahun' => 2025])->save();
        $ids = fn (array $f) => collect(app(MonitoringSpt::class)->baris($f))->flatMap(fn ($g) => array_column($g['spt'], 'id'))->all();

        $this->assertEqualsCanonicalizing([$this->m[2]->id, $lama->id], $ids(['penanda' => 'terlambat', 'tw' => 2]));
        $this->assertSame([], $ids(['penanda' => 'terlambat', 'tw' => 1]));
        $this->assertSame([$this->m[2]->id], $ids(['tw' => 2, 'status' => 'penyusunan_laporan'])); // control: without the chip the year stays
        $this->assertSame(2, app(MonitoringSpt::class)->jumlah(['penanda' => 'terlambat', 'tw' => 2]));
        $this->assertSame(2, app(MonitoringSpt::class)->jumlah(['penanda' => 'terlambat', 'tw' => 2, 'tahun' => 2025])); // the chip ignores the selected year, as before
    }

    public function test_invalid_filter_values_fall_back(): void
    {
        $monitoring = app(MonitoringSpt::class);
        $bawaan = ['tahun' => 2026, 'tw' => null, 'irban' => null, 'status' => null, 'jenis' => null, 'kesesuaian' => null, 'penanda' => null, 'penjelasan' => null];
        $this->assertSame($bawaan, $monitoring->filter([]));

        $invalid = ['tahun' => 'abc', 'tw' => 5, 'irban' => 99999, 'status' => 'draf', 'jenis' => 'x', 'kesesuaian' => 'foo', 'penanda' => 'non_pkpt', 'penjelasan' => 'ya', 'lain' => 1];
        $this->assertSame($bawaan, $monitoring->filter($invalid));
        $this->assertSame(['i1' => [1, 2], 'i3' => [4], 'i2' => [3], 'luar' => [5, 6]], $this->ringkas($invalid));
        $this->assertSame(['tahun' => 2026, 'tw' => null, 'irban' => null, 'status' => null, 'jenis' => null, 'kesesuaian' => null, 'penanda' => null, 'penjelasan' => null],
            $monitoring->filter(['tahun' => ['2025'], 'tw' => '0', 'status' => ['selesai']]));

        $valid = $monitoring->filter(['tahun' => '2025', 'tw' => '3', 'irban' => (string) $this->b->id, 'status' => 'selesai', 'jenis' => (string) $this->j1->id,
            'kesesuaian' => 'non_pkpt', 'penanda' => 'terlambat', 'penjelasan' => 'terbuka']);
        $this->assertSame(['tahun' => 2025, 'tw' => 3, 'irban' => $this->b->id, 'status' => 'selesai', 'jenis' => $this->j1->id,
            'kesesuaian' => 'non_pkpt', 'penanda' => 'terlambat', 'penjelasan' => 'terbuka'], $valid);
    }

    public function test_query_count_is_constant(): void
    {
        $monitoring = app(MonitoringSpt::class);
        $hitung = function () use ($monitoring) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $monitoring->baris(['penanda' => 'terlambat']);
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };
        $hitung(); // settings cache warm-up
        $awal = $hitung();

        $item = PkptItem::factory()->create(['irban_id' => $this->a->id, 'jenis_penugasan_id' => $this->j1->id]);
        JadwalItem::geser($item, '2026-04-01', '2026-04-20');
        Spt::factory()->diterima()->count(50)->create(['irban_id' => $this->a->id, 'pkpt_item_id' => $item->id, 'jenis_penugasan_id' => $this->j1->id,
            'tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-10', 'dibuat_oleh' => User::factory()->create()->id]);

        $this->assertSame($awal, $hitung());
    }

    /** PRD §10 sizing: a year of 1.000 accepted SPT still costs the same queries (measured count recorded in decisions/fase-8.md). */
    public function test_query_count_with_1000_spt(): void
    {
        $monitoring = app(MonitoringSpt::class);
        $monitoring->baris([]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $monitoring->baris([]);
        $awal = count(DB::getQueryLog());

        $items = PkptItem::factory()->count(20)->create(['irban_id' => $this->a->id, 'jenis_penugasan_id' => $this->j1->id]);
        $user = User::factory()->create()->id;
        Spt::factory()->diterima()->count(994)->sequence(fn ($s) => ['pkpt_item_id' => $s->index % 5 ? $items[$s->index % 20]->id : null])
            ->create(['irban_id' => $this->b->id, 'jenis_penugasan_id' => $this->j2->id, 'tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-10', 'dibuat_oleh' => $user]);
        $this->assertSame(1000, Spt::where('status_pengajuan', 'diterima')->where('tahun', 2026)->count()); // m1..m6 + 994

        DB::flushQueryLog();
        $groups = $monitoring->baris([]);
        DB::disableQueryLog();

        $this->assertSame($awal, count(DB::getQueryLog()));
        $this->assertSame(1000, array_sum(array_map(fn ($g) => count($g['spt']), $groups)));
    }

    /** barisSpt() loads the relations it reads by itself: a caller that passes bare SPT still gets a constant query count. */
    public function test_baris_spt_loads_its_own_relations(): void
    {
        $monitoring = app(MonitoringSpt::class);
        $hitung = function (int $berapa) use ($monitoring) {
            $spts = Spt::where('status_pengajuan', 'diterima')->orderBy('id')->take($berapa)->get(); // bare: no relations loaded
            DB::flushQueryLog();
            DB::enableQueryLog();
            $baris = $monitoring->barisSpt($spts);
            DB::disableQueryLog();

            return [count(DB::getQueryLog()), $baris];
        };
        $hitung(1); // settings cache warm-up
        [$satu, $baris] = $hitung(1);
        [$semua, $banyak] = $hitung(6);

        $this->assertCount(1, $baris);
        $this->assertCount(6, $banyak);
        $this->assertSame($satu, $semua);
        $this->assertContains('Irban A', array_column($banyak, 'irban'));
    }

    public function test_untuk_banyak_equals_untuk_for_every_spt(): void
    {
        $penanda = app(PenandaSpt::class);
        $semua = Spt::orderBy('id')->get();
        $harapan = $semua->mapWithKeys(fn (Spt $s) => [$s->id => $penanda->untuk($s->fresh())])->all();

        $this->assertSame($harapan, $penanda->untukBanyak($semua));
        $this->assertSame($harapan, $penanda->untukBanyak(collect(Spt::orderBy('id')->get()->all()))); // plain Collection works too
    }

    /** Replace symbolic irban/jenis names in a filter with ids. */
    private function ids(array $filter): array
    {
        $map = ['A' => $this->a->id, 'B' => $this->b->id, 'J1' => $this->j1->id, 'J2' => $this->j2->id];
        foreach (['irban', 'jenis'] as $k) {
            if (isset($filter[$k])) {
                $filter[$k] = $map[$filter[$k]];
            }
        }

        return $filter;
    }
}
