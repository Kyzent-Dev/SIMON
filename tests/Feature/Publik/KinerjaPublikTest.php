<?php

namespace Tests\Feature\Publik;

use App\Models\User;
use App\Services\Publik\JadwalPublik;
use App\Services\Publik\RealisasiPublik;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** PRD §10: public pages < 2 s. 5.000 SPT bulk-inserted; set KINERJA=1 to print the timings. */
class KinerjaPublikTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_level_calls_with_5000_spt_stay_under_two_seconds(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00', 'Asia/Jakarta'));
        $now = now();
        $user = User::factory()->create()->id;
        $irban = collect(range(1, 6))->map(fn ($n) => DB::table('irban')->insertGetId(['nama' => "Irban $n", 'kode_nomor' => "K$n"]));
        $jenis = collect(range(1, 8))->map(fn ($n) => DB::table('jenis_penugasan')->insertGetId(['nama' => "Jenis $n", 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF']));
        $kl = DB::table('klasifikasi')->whereIn('kode', ['REGULER', 'KHUSUS'])->pluck('id', 'kode');
        $kec = collect(range(1, 25))->map(fn ($n) => DB::table('objek')->insertGetId(['nama' => "Kecamatan $n", 'jenis' => 'kecamatan']));
        $desa = $kec->flatMap(fn ($k) => collect(range(1, 10))->map(fn ($n) => DB::table('objek')->insertGetId(['nama' => "Desa $k-$n", 'jenis' => 'desa', 'parent_id' => $k])));
        foreach ($kec as $n => $k) {
            DB::table('irban_wilayah')->insert(['irban_id' => $irban[$n % 6], 'tahun' => 2026, 'objek_id' => $k]);
        }
        $pkpt = DB::table('pkpt')->insertGetId(['tahun' => 2026, 'status' => 'disahkan']);
        $item = collect(range(0, 119))->map(fn ($n) => DB::table('pkpt_item')->insertGetId([
            'pkpt_id' => $pkpt, 'irban_id' => $irban[$n % 6], 'jenis_penugasan_id' => $jenis[$n % 8],
            'tanggal_mulai' => sprintf('2026-%02d-01', $n % 12 + 1), 'tanggal_selesai' => sprintf('2026-%02d-20', $n % 12 + 1), 'target_spt' => $n % 3 ?: null,
        ]));

        foreach (array_chunk(range(0, 4999), 500) as $chunk) {
            $rows = array_map(fn ($n) => [
                'irban_id' => $irban[$n % 6], 'klasifikasi_id' => $n % 10 === 0 ? $kl['KHUSUS'] : $kl['REGULER'], 'pkpt_item_id' => $item[$n % 120],
                'jenis_penugasan_id' => $jenis[$n % 120 % 8], 'tanggal_mulai' => sprintf('2026-%02d-%02d', $n % 12 + 1, $n % 20 + 1),
                'tanggal_selesai' => sprintf('2026-%02d-%02d', $n % 12 + 1, $n % 20 + 5), 'status_pengajuan' => 'diterima',
                'ditandai_selesai_pada' => $n % 4 === 0 ? null : $now, 'nomor_lengkap' => "P/$n", 'dibuat_oleh' => $user,
            ], $chunk);
            DB::table('spt')->insert($rows);
        }
        $spt = DB::table('spt')->pluck('id');
        foreach ($spt->chunk(500) as $chunk) {
            DB::table('spt_objek')->insert($chunk->values()->map(fn ($id) => ['spt_id' => $id, 'objek_id' => $desa[($id * 7) % $desa->count()]])->all());
        }

        $r = app(RealisasiPublik::class);
        $j = app(JadwalPublik::class);
        $f = ['tahun' => 2026];
        $ukur = function (callable $fn) {
            $t = microtime(true);
            $fn();

            return round((microtime(true) - $t) * 1000);
        };
        $waktu = [
            'realisasi' => $ukur(function () use ($r, $f) {
                $r->opsi();
                $r->ringkasan($f);
                $r->perBulan($f, false);
                $r->perBulan($f, true);
                $r->proporsi($f, 'irban');
                $r->proporsi($f, 'jenis');
                $r->rencanaVsRealisasi($f, null);
                $r->tabel($f, 'desc', 1);
            }),
            'realisasi_filter' => $ukur(function () use ($r, $kec) {
                $f = ['tahun' => 2026, 'tw' => 2, 'wilayah' => $kec[3], 'irban' => null, 'jenis' => null];
                $r->ringkasan($f);
                $r->perBulan($f, true);
                $r->proporsi($f, 'jenis');
                $r->rencanaVsRealisasi($f, 2);
                $r->tabel($f, 'asc', 3);
            }),
            'jadwal' => $ukur(function () use ($r, $j) {
                $r->opsi();
                $item = $j->item(2026, null);
                $j->linimasa($item, 2026);
                $j->bulanan($item, 2026);
                $j->wilayah(2026);
            }),
        ];
        if (getenv('KINERJA')) {
            fwrite(STDERR, 'Kinerja publik (ms): '.json_encode($waktu).' selesai publik='.$r->ringkasan($f)['selesai'].PHP_EOL);
        }

        // 5000 SPT: 1 in 4 not marked Selesai, 1 in 10 KHUSUS (overlapping on multiples of 20) → 5000 − 1250 − 500 + 250 = 3500 public.
        $this->assertSame(3500, $r->ringkasan($f)['selesai']);
        foreach ($waktu as $halaman => $ms) {
            $this->assertLessThan(2000, $ms, $halaman);
        }
    }
}
