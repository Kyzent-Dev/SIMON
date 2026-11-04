<?php

namespace Tests\Feature\Notifikasi;

use App\Enums\KejadianSpt;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\SptRiwayat;
use App\Models\User;
use App\Notifications\RangkumanNomorTerbit;
use App\Services\RangkumanNomorTerbit as Hitung;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** 1.1.7 Task 1: daily summary of numbers issued yesterday, bell only, Pimpinan only. */
class RangkumanNomorTerbitTest extends TestCase
{
    use RefreshDatabase;

    private function jam(string $waktu): CarbonImmutable
    {
        $this->travelTo($t = CarbonImmutable::parse($waktu, 'Asia/Jakarta'));

        return $t;
    }

    private function terbit(string $kejadian, string $waktu, ?Irban $irban = null, array $spt = []): Spt
    {
        $s = Spt::factory()->diterima()->create(['irban_id' => ($irban ?? Irban::factory()->create())->id, ...$spt]);
        $r = new SptRiwayat(['spt_id' => $s->id, 'kejadian' => $kejadian, 'oleh' => User::factory()->create()->id]);
        $r->forceFill(['created_at' => CarbonImmutable::parse($waktu, 'Asia/Jakarta'), 'updated_at' => now()])->save();

        return $s;
    }

    public function test_hitung_counts_diterima_and_manual_yesterday_only(): void
    {
        $hariIni = $this->jam('2026-11-04 06:00');
        $a = Irban::factory()->create(['nama' => 'Irban B']);
        $b = Irban::factory()->create(['nama' => 'Irban A']);
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-03 00:00:00', $a);
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-03 23:59:00', $b);
        $this->terbit(KejadianSpt::DICATAT_MANUAL, '2026-11-03 12:00:00', $b);
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-04 00:00:00', $a);
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-02 23:59:00', $a);

        $h = app(Hitung::class)->hitung($hariIni);

        $this->assertSame('2026-11-03', $h['tanggal']);
        $this->assertSame(3, $h['jumlah']);
        $this->assertSame(['Irban A' => 2, 'Irban B' => 1], $h['per_irban']);
    }

    public function test_hitung_skips_cancelled_and_imported(): void
    {
        $hariIni = $this->jam('2026-11-04 06:00');
        $batal = $this->terbit(KejadianSpt::DITERIMA, '2026-11-03 10:00:00');
        $batal->forceFill(['status_pengajuan' => 'dibatalkan'])->save();
        $this->terbit(KejadianSpt::DIIMPOR, '2026-11-03 10:00:00');

        $this->assertSame(0, app(Hitung::class)->hitung($hariIni)['jumlah']);
    }

    public function test_command_sends_bell_only_to_every_pimpinan(): void
    {
        $this->jam('2026-11-04 06:00');
        $p = User::factory()->pimpinan()->count(2)->create();
        $p[0]->update(['email' => 'p@example.test']);
        $admin = User::factory()->admin()->create();
        $irban = User::factory()->irban()->create(['irban_id' => Irban::factory()->create()->id]);
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-03 10:00:00');

        $this->assertSame(['database'], (new RangkumanNomorTerbit(['tanggal' => '2026-11-03', 'jumlah' => 1, 'per_irban' => []]))->via($p[0]));
        $this->artisan('notifikasi:harian')->assertSuccessful();

        foreach ($p as $u) {
            $this->assertSame(1, $u->notifications()->where('type', RangkumanNomorTerbit::class)->count());
        }
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame(0, $irban->notifications()->count());
    }

    public function test_payload(): void
    {
        $n = new RangkumanNomorTerbit(['tanggal' => '2026-11-03', 'jumlah' => 3, 'per_irban' => ['Irban A' => 2, 'Irban B' => 1]]);
        $d = $n->toArray(new User);

        $this->assertSame('rangkuman_nomor_terbit', $d['jenis']);
        $this->assertSame('3 nomor SPT terbit', $d['judul']);
        $this->assertSame('03 November 2026 · Irban A: 2, Irban B: 1', $d['konteks']);
        $this->assertStringContainsString('tampilan=spt', $d['url']);
        $this->assertStringContainsString('urut=terbit', $d['url']);
        $this->assertStringContainsString('tahun=2026', $d['url']);
    }

    public function test_no_notification_when_nothing_issued(): void
    {
        $this->jam('2026-11-04 06:00');
        $p = User::factory()->pimpinan()->create();

        $this->artisan('notifikasi:harian')->assertSuccessful();

        $this->assertSame(0, $p->notifications()->count());
    }

    public function test_second_run_same_day_sends_nothing(): void
    {
        $this->jam('2026-11-04 06:00');
        $p = User::factory()->pimpinan()->create();
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-03 10:00:00');

        $this->artisan('notifikasi:harian')->assertSuccessful();
        $this->artisan('notifikasi:harian')->assertSuccessful();
        $this->assertSame(1, $p->notifications()->count());

        $this->jam('2026-11-05 06:00');
        $this->terbit(KejadianSpt::DITERIMA, '2026-11-04 10:00:00');
        $this->artisan('notifikasi:harian')->assertSuccessful();
        $this->assertSame(2, $p->notifications()->count());
    }

    public function test_new_year_link_carries_previous_year(): void
    {
        $this->jam('2027-01-01 06:00');
        $p = User::factory()->pimpinan()->create();
        $this->terbit(KejadianSpt::DITERIMA, '2026-12-31 10:00:00');

        $this->artisan('notifikasi:harian')->assertSuccessful();

        $this->assertStringContainsString('tahun=2026', $p->notifications()->first()->data['url']);
    }
}
