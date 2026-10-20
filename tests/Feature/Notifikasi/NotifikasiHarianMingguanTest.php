<?php

namespace Tests\Feature\Notifikasi;

use App\Enums\KejadianSpt;
use App\Models\Irban;
use App\Models\PkptItemRevisi;
use App\Models\Spt;
use App\Models\SptDokumen;
use App\Models\SptRiwayat;
use App\Models\User;
use App\Notifications\PengingatSptFinal;
use App\Notifications\RingkasanMingguan;
use App\Notifications\SptTerlambat;
use App\Services\RingkasanMingguan as HitungRingkasan;
use App\Services\Settings;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Fase 6 Task 3: daily reminders and weekly summary. */
class NotifikasiHarianMingguanTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private User $user;

    private User $lain;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->irban = Irban::factory()->create();
        $this->user = User::factory()->irban()->create(['irban_id' => $this->irban->id]);
        $this->lain = User::factory()->irban()->create(['irban_id' => Irban::factory()->create()->id]);
    }

    private function jam(string $waktu): void
    {
        $this->travelTo(CarbonImmutable::parse($waktu, 'Asia/Jakarta'));
    }

    private function harian(): void
    {
        $this->artisan('notifikasi:harian')->assertSuccessful();
    }

    private function spt(array $attrs = []): Spt
    {
        return Spt::factory()->diterima()->create(['irban_id' => $this->irban->id, 'tanggal_mulai' => '2026-03-10', 'tanggal_selesai' => '2026-03-20', ...$attrs]);
    }

    private function dokumen(Spt $spt, string $jenis): void
    {
        SptDokumen::create(['spt_id' => $spt->id, 'jenis' => $jenis, 'path' => "spt/{$spt->id}/x.pdf", 'nama_asli' => 'x.pdf',
            'ukuran' => 10, 'sha256' => str_repeat('a', 64), 'diunggah_oleh' => User::factory()->create()->id]);
    }

    public function test_terlambat_sent_at_plus_15_not_plus_14(): void
    {
        $spt = $this->spt(['tanggal_mulai' => '2026-03-01']);
        $this->jam('2026-04-03 06:00');
        $this->harian();
        Notification::assertNotSentTo($this->user, SptTerlambat::class);

        $this->jam('2026-04-04 06:00');
        $this->harian();
        Notification::assertSentTo($this->user, SptTerlambat::class);
        Notification::assertNotSentTo($this->lain, SptTerlambat::class);
        $this->assertNotNull($spt->fresh()->terlambat_diberitahu_pada);
    }

    public function test_boundary_2330_wib(): void
    {
        $this->spt(['tanggal_mulai' => '2026-03-01']);
        $this->jam('2026-04-03 23:30');
        $this->harian();
        Notification::assertNotSentTo($this->user, SptTerlambat::class);
        $this->jam('2026-04-04 23:30');
        $this->harian();
        Notification::assertSentTo($this->user, SptTerlambat::class);
    }

    public function test_terlambat_respects_setting_and_sends_once(): void
    {
        $spt = $this->spt(['tanggal_mulai' => '2026-03-01']);
        app(Settings::class)->set('penanda.batas_terlambat_hari', 3);
        $this->jam('2026-03-23 06:00');
        $this->harian();
        Notification::assertNotSentTo($this->user, SptTerlambat::class);
        $this->jam('2026-03-24 06:00');
        $this->harian();
        $this->harian();
        Notification::assertSentToTimes($this->user, SptTerlambat::class, 1);
        $this->assertNotNull($spt->fresh()->terlambat_diberitahu_pada);
    }

    public function test_terlambat_not_for_selesai_or_not_diterima(): void
    {
        $this->spt(['tanggal_mulai' => '2026-03-01', 'ditandai_selesai_pada' => now()]);
        Spt::factory()->menungguNomor()->create(['irban_id' => $this->irban->id, 'tanggal_selesai' => '2026-03-20']);
        $this->jam('2026-05-01 06:00');
        $this->harian();
        Notification::assertNotSentTo($this->user, SptTerlambat::class);
    }

    public function test_pengingat_final_at_plus_3_days_once(): void
    {
        $spt = $this->spt(['tanggal_mulai' => '2026-03-10', 'tanggal_selesai' => '2026-12-31']);
        $this->jam('2026-03-12 23:30');
        $this->harian();
        Notification::assertNotSentTo($this->user, PengingatSptFinal::class);

        $this->jam('2026-03-13 06:00');
        $this->dokumen($spt, 'draf');
        $this->harian();
        $this->harian();
        Notification::assertSentToTimes($this->user, PengingatSptFinal::class, 1);
        Notification::assertNotSentTo($this->lain, PengingatSptFinal::class);
        $this->assertNotNull($spt->fresh()->pengingat_final_pada);
    }

    public function test_pengingat_final_skipped_with_final_or_without_mulai(): void
    {
        $this->dokumen($this->spt(['tanggal_selesai' => '2026-12-31']), 'final');
        $this->spt(['tanggal_mulai' => null, 'tanggal_selesai' => '2026-12-31']);
        $this->jam('2026-04-01 06:00');
        $this->harian();
        Notification::assertNotSentTo($this->user, PengingatSptFinal::class);
    }

    public function test_harian_output(): void
    {
        $this->spt(['tanggal_mulai' => '2026-03-01']);
        $this->jam('2026-04-04 06:00');
        $this->artisan('notifikasi:harian')->expectsOutput('Terlambat: 1 notifikasi, pengingat SPT final: 1 notifikasi.')->assertSuccessful();
    }

    private function riwayat(Spt $spt, string $kejadian, string $waktu): void
    {
        $r = new SptRiwayat(['spt_id' => $spt->id, 'kejadian' => $kejadian, 'oleh' => $this->user->id]);
        $r->forceFill(['created_at' => CarbonImmutable::parse($waktu, 'Asia/Jakarta'), 'updated_at' => now()])->save();
    }

    public function test_hitung_counts_and_window_edges(): void
    {
        // Run Monday 2026-04-13: window Mon 2026-04-06 00:00 .. Sun 2026-04-12 23:59.
        $this->jam('2026-04-13 07:00');
        $a = $this->spt(['tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-05']);
        $this->riwayat($a, KejadianSpt::DITERIMA, '2026-04-06 00:00');
        $this->riwayat($a, KejadianSpt::DICATAT_MANUAL, '2026-04-12 23:59');
        $this->riwayat($a, KejadianSpt::DITERIMA, '2026-04-05 23:59'); // before
        $this->riwayat($a, KejadianSpt::DITERIMA, '2026-04-13 00:00'); // Monday of the run
        $this->riwayat($a, KejadianSpt::DITANDAI_SELESAI, '2026-04-08 10:00');
        $this->riwayat($a, KejadianSpt::DITANDAI_SELESAI, '2026-04-13 00:00');
        $this->riwayat($a, KejadianSpt::DIAJUKAN, '2026-04-08 10:00');
        Spt::factory()->menungguNomor()->count(2)->create();
        PkptItemRevisi::factory()->count(3)->create();
        PkptItemRevisi::factory()->disetujui()->create();

        $h = app(HitungRingkasan::class)->hitung(CarbonImmutable::parse('2026-04-13', 'Asia/Jakarta'));
        $this->assertSame(2, $h['nomor']);
        $this->assertSame(1, $h['selesai']);
        $this->assertSame(2, $h['menunggu_nomor']);
        $this->assertSame(3, $h['revisi']);
        $this->assertSame(0, $h['terlambat']);
    }

    public function test_hitung_terlambat_list_capped_at_10_oldest_first(): void
    {
        $this->jam('2026-04-13 07:00');
        foreach (range(1, 12) as $i) {
            $this->spt(['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => sprintf('2026-02-%02d', $i)]);
        }
        $this->spt(['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-04-10']); // not terlambat

        $h = app(HitungRingkasan::class)->hitung(CarbonImmutable::parse('2026-04-13', 'Asia/Jakarta'));
        $this->assertSame(12, $h['terlambat']);
        $this->assertCount(10, $h['daftar']);
        $this->assertStringContainsString($this->irban->nama.' · 71 hari sejak tanggal selesai', $h['daftar'][0]);
        $this->assertSame(2, $h['lainnya']);
    }

    public function test_weekly_command_sends_to_pimpinan_only(): void
    {
        $pimpinan = User::factory()->pimpinan()->count(2)->create();
        $this->jam('2026-04-13 07:00');
        $this->artisan('notifikasi:ringkasan-mingguan')->assertSuccessful();
        foreach ($pimpinan as $p) {
            Notification::assertSentTo($p, RingkasanMingguan::class, fn (RingkasanMingguan $n) => $n->konteks() === '06 April 2026 – 12 April 2026'
                && str_contains(implode("\n", $n->isi()), 'Nomor SPT terbit: 0'));
        }
        Notification::assertNotSentTo($this->user, RingkasanMingguan::class);
    }

    public function test_weekly_window_is_monday_based_regardless_of_locale(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();
        $this->jam('2026-04-13 07:00');
        app()->setLocale('en_US');
        Carbon::setLocale('en_US');
        $this->artisan('notifikasi:ringkasan-mingguan')->assertSuccessful();
        Notification::assertSentTo($pimpinan, RingkasanMingguan::class, fn (RingkasanMingguan $n) => $n->konteks() === '06 April 2026 – 12 April 2026');
    }

    public function test_weekly_command_reports_zero_recipients_accurately(): void
    {
        $this->jam('2026-04-13 07:00');
        $this->artisan('notifikasi:ringkasan-mingguan')->expectsOutput('Tidak ada pimpinan penerima; ringkasan tidak dikirim.')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_schedule_registered(): void
    {
        $events = collect(app(Schedule::class)->events());
        foreach (['notifikasi:harian' => '0 6 * * *', 'notifikasi:ringkasan-mingguan' => '0 7 * * 1'] as $cmd => $cron) {
            $e = $events->first(fn ($e) => str_contains($e->command, $cmd));
            $this->assertNotNull($e, $cmd);
            $this->assertSame($cron, $e->expression);
            $this->assertSame('Asia/Jakarta', (string) $e->timezone);
            $this->assertTrue($e->withoutOverlapping);
        }
    }
}
