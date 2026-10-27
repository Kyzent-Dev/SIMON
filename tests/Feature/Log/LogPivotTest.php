<?php

namespace Tests\Feature\Log;

use App\Actions\MasterData\SaveIrbanTahunan;
use App\Actions\Spt\ImporSpt;
use App\Actions\Spt\SimpanSpt;
use App\Models\Aktivitas;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Klasifikasi;
use App\Models\Objek;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\SptPersonel;
use App\Models\SptRiwayat;
use App\Models\User;
use App\Support\CachePublik;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Impor\SptImportTestHelpers;
use Tests\TestCase;

/** Fase 11 Task 5 (E7): pivot changes spt_objek and irban_anggota get an explicit `updated` log row; the impor stays unlogged. */
class LogPivotTest extends TestCase
{
    use RefreshDatabase, SptImportTestHelpers;

    private function sptBaru(array $objekIds): array
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Asia/Jakarta'));
        $irban = Irban::factory()->create();
        $user = User::factory()->irban()->create();
        $user->forceFill(['irban_id' => $irban->id])->save();
        $ppj = Pegawai::factory()->create();
        $anggota = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $irban->id, 'tahun' => 2026, 'ppj_pegawai_id' => $ppj->id])->anggota()->attach($anggota->id);
        $data = fn (array $ids) => [
            'klasifikasi_id' => Klasifikasi::where('kode', 'REGULER')->value('id'), 'diinput_oleh' => $anggota->id, 'objek_ids' => $ids,
            'personel' => [
                ['pegawai_id' => $ppj->id, 'peran' => 'ketua', 'sumber' => 'tambahan', 'tim_id' => null, 'ikut' => true],
                ['pegawai_id' => $anggota->id, 'peran' => 'anggota', 'sumber' => 'tambahan', 'tim_id' => null, 'ikut' => true],
            ],
        ];

        return [$user, $anggota, $data, app(SimpanSpt::class)->handle($user, null, $data($objekIds), 'draf')];
    }

    public function test_objek_spt_dicatat(): void
    {
        $a = Objek::factory()->create(['nama' => 'Objek A', 'jenis' => 'dinas_badan']);
        $b = Objek::factory()->create(['nama' => 'objek B', 'jenis' => 'dinas_badan']);
        [$user, $anggota, $data, $spt] = $this->sptBaru([$a->id]);

        // Create with objek: one row, old [] → ['Objek A'].
        $dibuat = Aktivitas::where('subject_id', $spt->id)->where('subject_type', $spt->getMorphClass())->where('event', 'updated')->sole();
        $this->assertSame([], $dibuat->properties['old']['objek']);
        $this->assertSame(['Objek A'], $dibuat->properties['attributes']['objek']);

        app(SimpanSpt::class)->handle($user, $spt, $data([$b->id, $a->id]), 'draf');

        $baris = Aktivitas::where('subject_id', $spt->id)->where('subject_type', $spt->getMorphClass())->where('event', 'updated')->latest('id')->first();
        $this->assertSame(['Objek A'], $baris->properties['old']['objek']);
        $this->assertSame(['Objek A', 'objek B'], $baris->properties['attributes']['objek']);
        $this->assertSame($user->id, $baris->causer_id);
        $this->assertSame($anggota->id, $baris->diinput_oleh);
        $this->assertSame(2, Aktivitas::where('subject_id', $spt->id)->where('subject_type', $spt->getMorphClass())->where('event', 'updated')->count());
    }

    public function test_objek_tidak_berubah_tidak_dicatat(): void
    {
        $a = Objek::factory()->create(['nama' => 'Objek A', 'jenis' => 'dinas_badan']);
        [$user, , $data, $spt] = $this->sptBaru([$a->id]);
        $sebelum = Aktivitas::max('id');

        app(SimpanSpt::class)->handle($user, $spt, $data([$a->id]), 'draf');

        $this->assertSame(0, Aktivitas::where('id', '>', $sebelum)->get()->filter(fn ($x) => isset($x->properties['attributes']['objek']))->count());
    }

    public function test_anggota_irban_tahunan_dicatat(): void
    {
        $admin = User::factory()->admin()->create();
        Auth::login($admin);
        $irban = Irban::factory()->create();
        $p1 = Pegawai::factory()->create(['nama_bergelar' => 'Budi']);
        $p2 = Pegawai::factory()->create(['nama_bergelar' => 'Andi']);
        $tahun = (int) now('Asia/Jakarta')->year;

        $row = app(SaveIrbanTahunan::class)->handle($irban, $tahun, null, [$p1->id]);
        $baris = Aktivitas::where('subject_type', $row->getMorphClass())->where('subject_id', $row->id)->where('event', 'updated')->sole();
        $this->assertSame([], $baris->properties['old']['anggota']);
        $this->assertSame(['Budi'], $baris->properties['attributes']['anggota']);

        app(SaveIrbanTahunan::class)->handle($irban, $tahun, null, [$p1->id, $p2->id]);
        $baris = Aktivitas::where('subject_type', $row->getMorphClass())->where('subject_id', $row->id)->where('event', 'updated')->latest('id')->first();
        $this->assertSame(['Budi'], $baris->properties['old']['anggota']);
        $this->assertSame(['Andi', 'Budi'], $baris->properties['attributes']['anggota']);
        $this->assertSame($admin->id, $baris->causer_id);

        $n = Aktivitas::all()->filter(fn ($x) => isset($x->properties['attributes']['anggota']))->count();
        app(SaveIrbanTahunan::class)->handle($irban, $tahun, null, [$p2->id, $p1->id]);
        $this->assertSame($n, Aktivitas::all()->filter(fn ($x) => isset($x->properties['attributes']['anggota']))->count());
    }

    public function test_impor_tidak_mencatat_pivot(): void
    {
        Notification::fake();
        $this->seedImpor();
        $ppj = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $this->irban->id, 'tahun' => 2025, 'ppj_pegawai_id' => $ppj->id]);
        $rows = $this->analisis([$this->baris(['objek' => 'Kecamatan Lawang Kidul'])]);

        $this->assertSame(1, app(ImporSpt::class)->handle($this->admin, $rows, 'spt-lama.xlsx'));

        $this->assertSame(1, Spt::count());
        $this->assertSame(0, Aktivitas::all()->filter(fn ($x) => isset($x->properties['attributes']['objek']))->count());
    }

    public function test_transaksi_gagal_tidak_meninggalkan_log_pivot(): void
    {
        $a = Objek::factory()->create(['nama' => 'Objek A', 'jenis' => 'dinas_badan']);
        $b = Objek::factory()->create(['nama' => 'Objek B', 'jenis' => 'dinas_badan']);
        [$user, , $data, $spt] = $this->sptBaru([$a->id]);
        $sebelum = Aktivitas::count();

        // The pivot log row is written before the riwayat row; failing there must roll the pivot and its log row back together.
        SptRiwayat::creating(fn () => throw new \DomainException('gagal'));
        try {
            app(SimpanSpt::class)->handle($user, $spt, $data([$b->id]), 'draf');
            $this->fail('Transaksi seharusnya gagal.');
        } catch (\DomainException $e) {
            $this->assertSame('gagal', $e->getMessage());
        }

        $this->assertSame($sebelum, Aktivitas::count());
        $this->assertSame([$a->id], $spt->objek()->pluck('objek.id')->all());
        $this->assertTrue(Aktivitas::where('subject_id', $spt->id)->where('event', 'updated')->exists(), 'kontrol: log pembuatan SPT tetap ada');
    }

    public function test_simpan_ulang_tanpa_perubahan_objek_tidak_membaca_pivot_lagi(): void
    {
        $a = Objek::factory()->create(['nama' => 'Objek A', 'jenis' => 'dinas_badan']);
        [$user, , $data, $spt] = $this->sptBaru([$a->id]);
        $hitung = function () use ($user, $spt, $data, $a) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(SimpanSpt::class)->handle($user, $spt, $data([$a->id]), 'draf');
            DB::disableQueryLog();

            return count(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'inner join') && str_contains($q['query'], 'spt_objek')));
        };

        // One read of the old names (before sync); the old second read after sync is gone.
        $this->assertSame(1, $hitung());
    }

    public function test_simpan_ulang_hanya_mencatat_personel_yang_berubah(): void
    {
        [$user, $anggota, $data, $spt] = $this->sptBaru([]);
        $barisLog = fn () => Aktivitas::where('subject_type', (new SptPersonel)->getMorphClass())->count();
        $sebelum = $barisLog();
        $this->assertSame(2, $sebelum, 'kontrol: pembuatan awal mencatat dua baris personel');

        app(SimpanSpt::class)->handle($user, $spt, $data([]), 'draf');
        $this->assertSame($sebelum, $barisLog());

        $d = $data([]);
        $d['personel'][1]['peran'] = 'supervisor_dalnis';
        app(SimpanSpt::class)->handle($user, $spt, $d, 'draf');
        $this->assertSame($sebelum + 2, $barisLog()); // only the changed row: one deleted, one created
        $this->assertSame(2, $spt->personel()->count());
    }

    public function test_pivot_menyegarkan_cache_publik_setelah_commit(): void
    {
        config(['cache.default' => 'array']);
        Cache::flush();
        $a = Objek::factory()->create(['nama' => 'Objek A', 'jenis' => 'dinas_badan']);
        $b = Objek::factory()->create(['nama' => 'Objek B', 'jenis' => 'dinas_badan']);
        [$user, , $data, $spt] = $this->sptBaru([$a->id]);

        $v = CachePublik::versi();
        app(SimpanSpt::class)->handle($user, $spt, $data([$b->id]), 'draf');
        $this->assertGreaterThan($v, CachePublik::versi());

        Notification::fake();
        $this->seedImpor();
        $ppj = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $this->irban->id, 'tahun' => 2025, 'ppj_pegawai_id' => $ppj->id]);
        $rows = $this->analisis([$this->baris(['objek' => 'Kecamatan Lawang Kidul'])]);
        $v = CachePublik::versi();
        app(ImporSpt::class)->handle($this->admin, $rows, 'spt-lama.xlsx');
        $this->assertGreaterThan($v, CachePublik::versi());
    }
}
