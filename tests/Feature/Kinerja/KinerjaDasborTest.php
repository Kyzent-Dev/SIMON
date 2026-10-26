<?php

namespace Tests\Feature\Kinerja;

use App\Livewire\Irban\Dasbor as IrbanDasbor;
use App\Livewire\Pimpinan\Dasbor as PimpinanDasbor;
use App\Models\User;
use App\Services\MonitoringSpt;
use Carbon\CarbonImmutable;
use Database\Seeders\KinerjaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Structural performance checks (Fase 11, Task 3) on KinerjaSeeder data, not wall-clock: the dashboards and monitoring keep a
 * constant number of queries as SPT grow, and their numbers stay what the code computed when this test was written.
 */
class KinerjaDasborTest extends TestCase
{
    use RefreshDatabase;

    private const HALAMAN = ['/pimpinan' => 'pimpinan', '/pimpinan/monitoring' => 'pimpinan', '/irban' => 'irban1', '/bagian-umum' => 'bagianumum'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00', 'Asia/Jakarta'));
    }

    private function isiData(int $perTahun): void
    {
        $seeder = new KinerjaSeeder;
        $seeder->sptPerTahun = $perTahun;
        $seeder->jumlahTahun = 3;
        $seeder->run();
    }

    /** @return array<string, int> queries of a warm GET per page */
    private function jumlahQuery(): array
    {
        $hasil = [];
        foreach (self::HALAMAN as $url => $username) {
            $user = User::where('username', $username)->sole();
            $this->flushSession(); // the session keeps the previous user's password hash (AuthenticateSession)
            $this->actingAs($user)->get($url)->assertOk();
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($user)->get($url)->assertOk();
            $hasil[$url] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }

        return $hasil;
    }

    public function test_jumlah_query_konstan(): void
    {
        // Two data sizes in one test: the small set lives in a nested transaction (savepoint) that is rolled back.
        DB::beginTransaction();
        try {
            $this->isiData(10);
            $kecil = $this->jumlahQuery();
        } finally {
            DB::rollBack();
        }

        $this->isiData(100);
        $this->assertSame(300, DB::table('spt')->where('status_pengajuan', 'diterima')->count());
        $this->assertSame($kecil, $this->jumlahQuery());
    }

    public function test_hasil_sama(): void
    {
        $this->isiData(100);
        $pimpinan = User::where('username', 'pimpinan')->sole();
        $irban = User::where('username', 'irban1')->sole();

        $kartu = fn ($h) => array_column($h->viewData('kartu'), 'nilai', 'label');
        $this->assertSame(self::KARTU_PIMPINAN, $kartu(Livewire::actingAs($pimpinan)->test(PimpinanDasbor::class)));
        $this->assertSame(self::KARTU_IRBAN, $kartu(Livewire::actingAs($irban)->test(IrbanDasbor::class)));

        $ringkas = function (array $filter) {
            $baris = array_merge(...array_column(app(MonitoringSpt::class)->baris($filter), 'spt'));
            $isi = array_map(fn ($b) => [$b['id'], $b['status']->value, $b['kesesuaian'], $b['penanda']], $baris);

            return [count($baris), md5(json_encode($isi))];
        };
        $this->assertSame(self::MONITORING, $ringkas(['tahun' => 2026]));
        $this->assertSame(self::MONITORING_TERLAMBAT, $ringkas(['tahun' => 2026, 'penanda' => 'terlambat']));
        $this->assertSame(self::MONITORING_2024, $ringkas(['tahun' => 2024]));
    }

    // Captured from the code before the Fase 11 Task 3 changes (2026-10-26 10:00 Jakarta, 3 × 100 accepted SPT).
    private const KARTU_PIMPINAN = ['Berjalan' => 23, 'Selesai' => 77, 'Terlambat' => 21, 'Tidak Sesuai PKPT' => 11,
        'Permintaan Penjelasan Terbuka' => 40, 'Perubahan PKPT Menunggu' => 0];

    private const KARTU_IRBAN = ['Draf' => 2, 'Menunggu Nomor' => 3, 'Perlu Revisi' => 1, 'Berjalan' => 5, 'Selesai' => 15, 'Terlambat' => 3];

    /** [rows, md5 of [id, status, kesesuaian, penanda] per row] */
    private const MONITORING = [100, 'fb7e41ee9c4968a433181aa4c1ee6404'];

    private const MONITORING_TERLAMBAT = [21, 'cef3ee27afbc26d731de6f099e4a3533'];

    private const MONITORING_2024 = [100, '8d10637ea38a78dff2daf0ec9453cd68'];
}
