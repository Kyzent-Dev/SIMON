<?php

namespace Tests\Feature\Notifikasi;

use App\Models\Irban;
use App\Models\User;
use App\Notifications\NotifikasiSimon;
use App\Services\PenerimaNotifikasi;
use App\Support\JenisNotifikasi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Fase 6 Task 1: notification foundation (tables, recipients, mail routing, base class, email theme). */
class FondasiNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_migrations_create_notifications_table_and_spt_idempotency_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('notifications', ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at']));
        $this->assertTrue(Schema::hasColumns('spt', ['terlambat_diberitahu_pada', 'pengingat_final_pada']));
    }

    public function test_penerima_returns_exactly_the_users_of_each_group(): void
    {
        $irban = Irban::factory()->create();
        $lain = Irban::factory()->create();
        $bu = User::factory()->bagianUmum()->count(2)->create();
        $pimpinan = User::factory()->pimpinan()->count(2)->create();
        $irbanUsers = User::factory()->irban()->count(2)->create(['irban_id' => $irban->id]);
        User::factory()->irban()->create(['irban_id' => $lain->id]);
        User::factory()->admin()->create(['irban_id' => $irban->id]);
        $pimpinanBerIrban = User::factory()->pimpinan()->create(['irban_id' => $irban->id]);

        $penerima = app(PenerimaNotifikasi::class);

        $this->assertEqualsCanonicalizing($bu->modelKeys(), $penerima->bagianUmum()->modelKeys());
        $this->assertEqualsCanonicalizing([...$pimpinan->modelKeys(), $pimpinanBerIrban->id], $penerima->pimpinan()->modelKeys());
        $this->assertEqualsCanonicalizing($irbanUsers->modelKeys(), $penerima->irban($irban->id)->modelKeys());
        $this->assertContainsOnlyInstancesOf(User::class, $penerima->irban($irban->id));
    }

    public function test_irban_mail_route_is_primary_plus_tembusan_without_duplicates_or_empty_values(): void
    {
        $irban = Irban::factory()->create([
            'email_notifikasi' => 'irban2@example.test',
            'email_tembusan' => ['kasubag@example.test', '', 'irban2@example.test', null, 'kasubag@example.test'],
        ]);
        $user = User::factory()->irban()->create(['irban_id' => $irban->id, 'email' => 'pribadi@example.test']);

        $this->assertSame(['irban2@example.test', 'kasubag@example.test'], $user->routeNotificationForMail());
    }

    public function test_mail_route_dedupes_case_insensitively_keeping_first_spelling(): void
    {
        $irban = Irban::factory()->create(['email_notifikasi' => 'Irban2@Example.test', 'email_tembusan' => ['irban2@example.test', 'Kasubag@example.test']]);
        $user = User::factory()->irban()->create(['irban_id' => $irban->id]);

        $this->assertSame(['Irban2@Example.test', 'Kasubag@example.test'], $user->routeNotificationForMail());
    }

    public function test_irban_mail_route_falls_back_to_user_email(): void
    {
        $irban = Irban::factory()->create(['email_notifikasi' => '', 'email_tembusan' => null]);
        $user = User::factory()->irban()->create(['irban_id' => $irban->id, 'email' => 'pribadi@example.test']);

        $this->assertSame(['pribadi@example.test'], $user->routeNotificationForMail());
        $this->assertSame(['database', 'mail'], (new NotifikasiUji)->via($user));
    }

    public function test_no_address_means_no_mail_channel(): void
    {
        $irban = Irban::factory()->create(['email_notifikasi' => null, 'email_tembusan' => []]);
        // users.email is NOT NULL, so unsaved models stand in for accounts without an address.
        $irbanUser = User::factory()->irban()->make(['irban_id' => $irban->id, 'email' => null]);
        $pimpinan = User::factory()->pimpinan()->make(['email' => '']);

        $this->assertNull($irbanUser->routeNotificationForMail());
        $this->assertNull($pimpinan->routeNotificationForMail());
        $this->assertSame(['database'], (new NotifikasiUji)->via($irbanUser));
        $this->assertSame(['database'], (new NotifikasiUji)->via($pimpinan));
    }

    public function test_other_roles_route_to_their_own_email(): void
    {
        $bu = User::factory()->bagianUmum()->create(['email' => 'bu@example.test']);

        $this->assertSame(['bu@example.test'], $bu->routeNotificationForMail());
        $this->assertSame(['database', 'mail'], (new NotifikasiUji)->via($bu));
    }

    public function test_to_array_has_exactly_the_bell_keys(): void
    {
        $data = (new NotifikasiUji('pengajuan_perlu_revisi', 'Lengkapi lampiran.'))->toArray(User::factory()->make());

        $this->assertSame([
            'jenis' => 'pengajuan_perlu_revisi',
            'judul' => 'Judul uji',
            'konteks' => 'Konteks uji',
            'url' => '/spt/7',
            'catatan' => 'Lengkapi lampiran.',
        ], $data);
    }

    public function test_queue_settings(): void
    {
        $n = new NotifikasiUji;

        $this->assertInstanceOf(ShouldQueueAfterCommit::class, $n);
        $this->assertContains(Queueable::class, class_uses_recursive($n));
        $this->assertSame(['database' => 'sync', 'mail' => config('queue.default')], $n->viaConnections());
    }

    public function test_nothing_is_stored_when_the_transaction_rolls_back(): void
    {
        $user = User::factory()->bagianUmum()->create();

        try {
            DB::transaction(function () use ($user) {
                $user->notify(new NotifikasiUji);
                throw new \RuntimeException('batal');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, $user->notifications()->count());

        DB::transaction(fn () => $user->notify(new NotifikasiUji));
        $this->assertSame('Judul uji', $user->notifications()->sole()->data['judul']);
        $this->assertCount(1, app('mailer')->getSymfonyTransport()->messages()); // array mailer, sync queue
    }

    public function test_to_mail_for_spt_jenis(): void
    {
        $user = User::factory()->bagianUmum()->create();
        $mail = (new NotifikasiUji('pengajuan_perlu_revisi', 'Lengkapi lampiran.'))->toMail($user);

        $this->assertSame('[SIMON-SPT] Judul uji', $mail->subject);
        $this->assertSame('Buka SPT', $mail->actionText);
        $this->assertSame(url('/spt/7'), $mail->actionUrl);

        $html = (string) $mail->render();
        $this->assertStringContainsString('Baris isi pertama.', $html);
        $this->assertStringContainsString('Catatan: Lengkapi lampiran.', $html);
        $this->assertStringContainsString('<blockquote', $html);
        $this->assertStringContainsString('Email otomatis dari SIMON-SPT Inspektorat Daerah Kabupaten Muara Enim. Jangan membalas email ini.', $html);
        $this->assertStringContainsString('SIMON-SPT', $html);
        $this->assertStringNotContainsString('Hello!', $html);
        $this->assertStringNotContainsString('Regards', $html);
        $this->assertStringContainsString('#082B23', $html); // header band mint-950 from the simon theme

        $text = (string) app(Markdown::class)->renderText($mail->markdown, $mail->data());
        $this->assertStringContainsString('Baris isi pertama.', $text);
        $this->assertStringContainsString('Buka SPT: '.url('/spt/7'), $text);
        $this->assertStringContainsString('Jangan membalas email ini.', $text);
    }

    public function test_to_mail_for_non_spt_jenis_without_catatan(): void
    {
        $mail = (new NotifikasiUji('ringkasan_mingguan'))->toMail(User::factory()->pimpinan()->create());

        $this->assertSame('Buka SIMON-SPT', $mail->actionText);
        $this->assertStringNotContainsString('Catatan:', (string) $mail->render());
    }

    public function test_subjek_can_override_judul(): void
    {
        $mail = (new NotifikasiUjiSubjek)->toMail(User::factory()->pimpinan()->create());

        $this->assertSame('[SIMON-SPT] Subjek khusus', $mail->subject);
    }

    public static function ikonProvider(): array
    {
        return [
            ['pengajuan_baru', 'inbox-arrow-down', 'info'],
            ['pengajuan_diterima', 'check-circle', 'success'],
            ['pengajuan_perlu_revisi', 'pencil-square', 'warning'],
            ['pengajuan_ditolak', 'x-circle', 'danger'],
            ['nomor_dibatalkan', 'no-symbol', 'danger'],
            ['revisi_jadwal_diajukan', 'calendar-days', 'info'],
            ['revisi_jadwal_disetujui', 'calendar-days', 'success'],
            ['revisi_jadwal_ditolak', 'calendar-days', 'danger'],
            ['perubahan_pkpt_diajukan', 'calendar-days', 'info'],
            ['perubahan_pkpt_disetujui', 'calendar-days', 'success'],
            ['perubahan_pkpt_ditolak', 'calendar-days', 'danger'],
            ['pengingat_perubahan_pkpt', 'clock', 'warning'],
            ['pengingat_sk_perubahan', 'document-arrow-up', 'warning'],
            ['usulan_perubahan_baru', 'inbox-arrow-down', 'info'],
            ['usulan_perubahan_diterapkan', 'check-circle', 'success'],
            ['usulan_perubahan_ditutup', 'x-circle', 'neutral'],
            ['spt_terlambat', 'clock', 'danger'],
            ['pengingat_spt_final', 'document-arrow-up', 'warning'],
            ['ringkasan_mingguan', 'chart-bar', 'neutral'],
            ['tidak_dikenal', 'bell', 'neutral'],
        ];
    }

    #[DataProvider('ikonProvider')]
    public function test_ikon_per_jenis(string $jenis, string $ikon, string $nada): void
    {
        $this->assertSame(['ikon' => $ikon, 'nada' => $nada], JenisNotifikasi::ikon($jenis));
    }
}

class NotifikasiUji extends NotifikasiSimon
{
    public function __construct(private string $jenisUji = 'pengajuan_baru', private ?string $catatanUji = null) {}

    public function jenis(): string
    {
        return $this->jenisUji;
    }

    public function judul(): string
    {
        return 'Judul uji';
    }

    public function konteks(): ?string
    {
        return 'Konteks uji';
    }

    public function url(): string
    {
        return '/spt/7';
    }

    public function isi(): array
    {
        return ['Baris isi pertama.', 'Baris isi kedua.'];
    }

    public function catatan(): ?string
    {
        return $this->catatanUji;
    }
}

class NotifikasiUjiSubjek extends NotifikasiUji
{
    protected function subjek(): string
    {
        return 'Subjek khusus';
    }
}
