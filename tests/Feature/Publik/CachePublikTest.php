<?php

namespace Tests\Feature\Publik;

use App\Actions\MasterData\SaveObjek;
use App\Actions\Pkpt\Perubahan\AjukanPerubahan;
use App\Actions\Pkpt\Perubahan\BuatPerubahan;
use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use App\Actions\Pkpt\Perubahan\SimpanOperasi;
use App\Actions\Spt\TandaiSelesai;
use App\Actions\Spt\TerimaPengajuan;
use App\Enums\Role;
use App\Livewire\Publik\Jadwal;
use App\Models\IrbanTahunan;
use App\Models\Objek;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Services\Publik\JadwalPublik;
use App\Services\Publik\RealisasiPublik;
use App\Services\Settings;
use App\Support\CachePublik;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use ReflectionProperty;
use RuntimeException;
use Tests\Support\PdfUji;
use Tests\TestCase;

/** Fase 11 Task 4 (E6): public aggregates are cached per version; every writer behind the public pages bumps the version. */
class CachePublikTest extends TestCase
{
    use PublikFixture {
        setUp as protected siapkanFixture;
    }
    use RefreshDatabase;

    protected function setUp(): void
    {
        $this->siapkanFixture();
        config(['cache.default' => 'array']);
        Cache::flush();
        Notification::fake();
    }

    /** Warm /realisasi and /jadwal, so a later change is only visible when the cache was invalidated. */
    private function panaskan(): void
    {
        $this->get('/realisasi?tahun=2026')->assertOk();
        $this->teks('/jadwal');
    }

    private function teks(string $url): string
    {
        if ($url === '/jadwal') { // the page lazy-loads the Livewire component
            return preg_replace('/\s+/', ' ', strip_tags(Livewire::test(Jadwal::class)->html()));
        }

        return preg_replace('/\s+/', ' ', strip_tags($this->get($url)->assertOk()->getContent()));
    }

    public function test_pangkas_menghapus_baris_kedaluwarsa_store_database(): void
    {
        $acara = collect(app(Schedule::class)->events())->first(fn (Event $e) => $e->description === 'cache:pangkas');
        $this->assertNotNull($acara);
        $this->assertSame('10 0 * * *', $acara->expression);
        $this->assertSame('Asia/Jakarta', (string) $acara->timezone);

        config(['cache.default' => 'database']);
        Cache::put('publik:1:lama', 'x', 60);
        DB::table('cache_locks')->insert(['key' => 'kunci-lama', 'owner' => 'uji', 'expiration' => now()->getTimestamp() + 60]);
        $this->travel(2)->hours();
        Cache::put('publik:2:baru', 'y', 3600);
        DB::table('cache_locks')->insert(['key' => 'kunci-baru', 'owner' => 'uji', 'expiration' => now()->getTimestamp() + 60]);

        config(['cache.default' => 'array']); // other stores: no-op
        $acara->run(app());
        $this->assertSame(2, DB::table('cache')->count());

        config(['cache.default' => 'database']);
        $acara->run(app());
        $this->assertSame([config('cache.prefix').'publik:2:baru'], DB::table('cache')->pluck('key')->all());
        $this->assertSame(['kunci-baru'], DB::table('cache_locks')->pluck('key')->all());
    }

    public function test_agregat_disimpan(): void
    {
        $svc = app(RealisasiPublik::class);
        $pertama = $svc->ringkasan(['tahun' => 2026, 'irban' => null]);

        DB::enableQueryLog();
        $this->assertSame($pertama, $svc->ringkasan(['irban' => null, 'tahun' => 2026]));
        $this->assertSame([], DB::getQueryLog());
    }

    public function test_bagian_umum_menerima_spt_menyegarkan_jadwal(): void
    {
        $kecZ = Objek::factory()->create(['nama' => 'Kec Zeta', 'jenis' => 'kecamatan']);
        $spt = Spt::factory()->menungguNomor()->create([
            'irban_id' => $this->b->id, 'pkpt_item_id' => $this->b1->id, 'jenis_penugasan_id' => $this->j2->id,
            'klasifikasi_id' => $this->reguler, 'tanggal_mulai' => '2026-05-04', 'tanggal_selesai' => '2026-05-08',
        ]);
        DB::table('spt_objek')->insert(['spt_id' => $spt->id, 'objek_id' => $kecZ->id]);
        $this->panaskan();
        $this->assertStringNotContainsString('Kec Zeta', $this->teks('/jadwal'));

        app(TerimaPengajuan::class)->handle(User::factory()->create(['role' => Role::BagianUmum]), $spt);

        $this->assertStringContainsString('Kec Zeta', $this->teks('/jadwal'));
    }

    public function test_irban_menandai_selesai_menyegarkan_realisasi(): void
    {
        $penginput = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $this->a->id, 'tahun' => 2026, 'ppj_pegawai_id' => $penginput->id]);
        $user = User::factory()->irban()->create();
        $user->forceFill(['irban_id' => $this->a->id])->save();
        $e6 = Spt::where('status_pengajuan', 'diterima')->whereNull('ditandai_selesai_pada')->where('pkpt_item_id', $this->a1->id)
            ->whereDate('tanggal_mulai', '2026-03-01')->firstOrFail();
        $this->panaskan();
        $this->assertStringContainsString('2 SPT selesai dari 3', $this->teks('/realisasi?tahun=2026'));

        app(TandaiSelesai::class)->handle($user, $e6, ['diinput_oleh' => $penginput->id, 'nomor_lhp' => 'LHP/1', 'tanggal_lhp' => '2026-03-10'], PdfUji::buat('lhp.pdf', 10));

        $this->assertStringContainsString('3 SPT selesai dari 3', $this->teks('/realisasi?tahun=2026'));
    }

    public function test_pimpinan_menyetujui_perubahan_pkpt_menyegarkan_jadwal(): void
    {
        $admin = User::factory()->admin()->create();
        $pimpinan = User::factory()->pimpinan()->create();
        $this->panaskan();
        $this->assertStringNotContainsString('12 Januari', $this->teks('/jadwal'));

        $p = app(BuatPerubahan::class)->handle($admin, $this->a1->pkpt, ['jenis' => 'perubahan', 'alasan' => 'Menyesuaikan jadwal.']);
        app(SimpanOperasi::class)->handle($admin, $p, null, ['aksi' => 'ubah', 'pkpt_item_id' => $this->a1->id, 'tanggal_mulai' => '2026-01-12', 'tanggal_selesai' => '2026-03-20']);
        app(PutuskanPerubahan::class)->handle($pimpinan, app(AjukanPerubahan::class)->handle($admin, $p), true, null);

        $this->assertStringContainsString('12 Januari', $this->teks('/jadwal'));
    }

    public function test_admin_mengubah_nama_objek_menyegarkan_jadwal(): void
    {
        $this->panaskan();
        $this->assertStringContainsString('Kec X', $this->teks('/jadwal'));

        app(SaveObjek::class)->handle($this->kecX, 'Kec Baru', 'kecamatan', null);

        $teks = $this->teks('/jadwal');
        $this->assertStringContainsString('Kec Baru', $teks);
        $this->assertStringNotContainsString('Kec X', $teks);
    }

    public function test_admin_mengubah_pengaturan_publik_menyegarkan_realisasi(): void
    {
        $this->panaskan();
        $this->assertStringNotContainsString('Desa X1', $this->teks('/realisasi?tahun=2026'));

        app(Settings::class)->set('publik.tampilkan_nama_objek', true);

        $this->assertStringContainsString('Desa X1', $this->teks('/realisasi?tahun=2026'));
    }

    public function test_dasar_triwulan_menyegarkan(): void
    {
        $sebelum = CachePublik::versi();
        app(Settings::class)->set('rekap.dasar_triwulan', 'tanggal_mulai');
        $this->assertGreaterThan($sebelum, CachePublik::versi());
    }

    public function test_rollback_tidak_menyegarkan(): void
    {
        $sebelum = CachePublik::versi();
        try {
            DB::transaction(function () {
                $this->v1->forceFill(['uraian_untuk' => 'diubah'])->save();
                throw new RuntimeException('gagal');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame($sebelum, CachePublik::versi());
    }

    public function test_pergantian_hari(): void
    {
        $this->travelTo(now('Asia/Jakarta')->startOfDay()->subMinute()); // 23:59 Jakarta, the day before
        $sebelum = CachePublik::versi();
        $this->travelTo(now('Asia/Jakarta')->addMinute()); // exactly 00:00, when dailyAt('00:00') is due

        $this->artisan('schedule:run');

        $this->assertGreaterThan($sebelum, CachePublik::versi());
    }

    public function test_muatan_cache_tidak_memuat_model(): void
    {
        $this->panaskan();
        $this->get('/')->assertOk();
        $kunci = $this->kunciPublik();
        $this->assertNotEmpty($kunci);
        foreach ($kunci as $k) {
            $this->assertStringNotContainsString('App\\Models\\', serialize(Cache::get($k)), $k);
        }
    }

    /** @return list<string> */
    private function kunciPublik(): array
    {
        $store = Cache::getStore();
        $semua = array_keys((new ReflectionProperty($store, 'storage'))->getValue($store));

        return array_values(array_filter($semua, fn ($k) => str_starts_with($k, 'publik:') && $k !== 'publik:versi'));
    }

    public function test_tahun_string_dan_int_satu_kunci(): void
    {
        $svc = app(RealisasiPublik::class);
        $svc->ringkasan(['tahun' => 2026, 'irban' => null]);
        $jumlah = count($this->kunciPublik());

        DB::enableQueryLog();
        $svc->ringkasan(['tahun' => '2026', 'irban' => null]);
        $this->assertSame([], DB::getQueryLog());
        $this->assertCount($jumlah, $this->kunciPublik());

        $svc->ringkasan(['tahun' => 2025, 'irban' => null]); // kontrol: tahun lain tetap kunci lain
        $this->assertCount($jumlah + 1, $this->kunciPublik());
    }

    public function test_penyegaran_menunggu_commit(): void
    {
        $sebelum = CachePublik::versi();
        DB::transaction(function () use ($sebelum) {
            $this->v1->forceFill(['uraian_untuk' => 'diubah'])->save();
            $this->assertSame($sebelum, CachePublik::versi(), 'belum commit: versi tetap');
        });

        $this->assertGreaterThan($sebelum, CachePublik::versi());
    }

    /** The database/Redis stores serialize with `serializable_classes => false`: an object in a cached value comes back as __PHP_Incomplete_Class. The array store used by default in tests does not serialize, which hid it. */
    public function test_cached_values_survive_a_serializing_store(): void
    {
        config(['cache.stores.array.serialize' => true]);
        Cache::forgetDriver('array');
        Cache::store('array')->put('uji', ['x' => 1], 60);
        $this->assertSame(['x' => 1], Cache::store('array')->get('uji'), 'control: the store round-trips plain arrays');

        $jadwal = app(JadwalPublik::class);
        $pertama = $jadwal->item(2026, null);
        $dariCache = $jadwal->item(2026, null); // second call: through the store
        $this->assertNotEmpty($pertama);
        $this->assertInstanceOf(CarbonImmutable::class, $dariCache[0]['mulai']);
        $this->assertEquals($pertama, $dariCache);
        // and the pages built on it render from cache
        $this->get('/jadwal')->assertOk()->assertDontSee('Jadwal gagal dimuat');
    }
}
