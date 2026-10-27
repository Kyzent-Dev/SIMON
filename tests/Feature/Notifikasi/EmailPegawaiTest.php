<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\Spt\BatalkanNomor;
use App\Actions\Spt\MintaRevisi;
use App\Actions\Spt\TerimaPengajuan;
use App\Actions\Spt\TolakPengajuan;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\EmailPegawai;
use App\Notifications\NomorDibatalkan;
use App\Notifications\PengajuanDiputuskan;
use App\Services\PenerimaNotifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Fase 9.2 Task 1: mail-only copy to the pegawai who entered the SPT. */
class EmailPegawaiTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private User $irbanUser;

    private User $bu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->irban = Irban::factory()->create(['email_notifikasi' => 'irban@example.test', 'email_tembusan' => ['tembusan@example.test']]);
        $this->irbanUser = User::factory()->irban()->create(['irban_id' => $this->irban->id, 'email' => 'akun@example.test']);
        $this->bu = User::factory()->bagianUmum()->create();
        Notification::fake();
    }

    private function spt(?Pegawai $p, string $state = 'menungguNomor'): Spt
    {
        return Spt::factory()->{$state}()->create(['irban_id' => $this->irban->id, 'diinput_oleh' => $p?->id]);
    }

    private function assertEmailKe(string $email, string $kelas = EmailPegawai::class): void
    {
        Notification::assertSentOnDemand($kelas, fn ($n, $channels, AnonymousNotifiable $to) => $to->routes['mail'] === $email && $channels === ['mail']);
        $this->assertCount(1, Notification::sentNotifications()[AnonymousNotifiable::class] ?? []);
    }

    /** @return list<callable(Spt): mixed> */
    private function aksi(): array
    {
        return [
            'diterima' => fn (Spt $s) => app(TerimaPengajuan::class)->handle($this->bu, $s),
            'perlu revisi' => fn (Spt $s) => app(MintaRevisi::class)->handle($this->bu, $s, 'Perbaiki objek pemeriksaan.'),
            'ditolak' => fn (Spt $s) => app(TolakPengajuan::class)->handle($this->bu, $s, 'Tidak sesuai ketentuan.'),
        ];
    }

    public function test_keputusan_is_emailed_to_the_pegawai_once(): void
    {
        foreach ($this->aksi() as $nama => $fn) {
            Notification::fake();
            $p = Pegawai::factory()->create(['email' => $nama.'@example.test']);
            $fn($this->spt($p));
            $this->assertEmailKe($nama.'@example.test');
            Notification::assertSentTo($this->irbanUser, PengajuanDiputuskan::class);
        }
    }

    public function test_nomor_dibatalkan_is_emailed_to_the_pegawai(): void
    {
        $p = Pegawai::factory()->create(['email' => 'p@example.test']);
        app(BatalkanNomor::class)->handle($this->bu, $this->spt($p, 'diterima'), 'Salah ketik perihal SPT');
        $this->assertEmailKe('p@example.test');
        Notification::assertSentTo($this->irbanUser, NomorDibatalkan::class);
    }

    public function test_skipped_without_email_inactive_or_missing_pegawai(): void
    {
        foreach ([Pegawai::factory()->create(), Pegawai::factory()->create(['email' => 'x@example.test', 'aktif' => false]), null] as $p) {
            app(TerimaPengajuan::class)->handle($this->bu, $this->spt($p));
        }
        Notification::assertSentTimes(PengajuanDiputuskan::class, 3);
        Notification::assertNotSentTo(new AnonymousNotifiable, EmailPegawai::class);
        $this->assertArrayNotHasKey(AnonymousNotifiable::class, Notification::sentNotifications());
    }

    public function test_skipped_when_email_equals_an_account_address_case_insensitive(): void
    {
        foreach (['IRBAN@example.test', 'Tembusan@Example.test'] as $email) {
            $p = Pegawai::factory()->create(['email' => $email]);
            app(BatalkanNomor::class)->handle($this->bu, $this->spt($p, 'diterima'), 'Salah ketik perihal SPT');
        }
        $this->assertArrayNotHasKey(AnonymousNotifiable::class, Notification::sentNotifications());
    }

    public function test_penerima_helper(): void
    {
        $penerima = app(PenerimaNotifikasi::class);
        $akun = $penerima->irban($this->irban->id);
        $this->assertNull($penerima->emailPegawai(null, $akun));
        // An irban without email_notifikasi is reached at the account email.
        $polos = User::factory()->irban()->create(['irban_id' => Irban::factory()->create(['email_notifikasi' => null, 'email_tembusan' => null])->id, 'email' => 'akun2@example.test']);
        $this->assertNull($penerima->emailPegawai(Pegawai::factory()->create(['email' => 'AKUN2@example.test']), $penerima->irban($polos->irban_id)));
        $this->assertSame('baru@example.test', $penerima->emailPegawai(Pegawai::factory()->create(['email' => 'baru@example.test']), $akun));
    }

    public function test_mail_has_greeting_closing_line_and_escapes(): void
    {
        $spt = $this->spt(Pegawai::factory()->create(), 'diterima');
        $n = new EmailPegawai(new NomorDibatalkan($spt, 'Alasan [x](http://jahat)'), 'Dr. [Budi](http://x), M.Si.');
        $mail = $n->toMail(new AnonymousNotifiable);
        $html = (string) $mail->render();

        $this->assertStringContainsString('Yth. Dr. ', $html);
        $this->assertStringNotContainsString('<a href="http://x"', $html);
        $this->assertStringNotContainsString('<a href="http://jahat"', $html);
        $this->assertStringContainsString('Anda menerima email ini karena tercatat sebagai penginput data di SINTAS.', $html);
        $this->assertSame(['mail'], $n->via(new AnonymousNotifiable));
        // The ordinary email stays without greeting.
        $this->assertStringNotContainsString('Yth.', (string) (new NomorDibatalkan($spt, 'Alasan lain'))->toMail($this->irbanUser)->render());
    }
}
