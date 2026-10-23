<?php

namespace Tests\Feature\Notifikasi;

use App\Actions\Pkpt\Perubahan\AjukanPerubahan;
use App\Actions\Pkpt\Perubahan\AturPengingatSk;
use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use App\Actions\Pkpt\Perubahan\SimpanSkPerubahan;
use App\Models\Irban;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\PkptPerubahanItem;
use App\Models\User;
use App\Notifications\PengingatPerubahanPkpt;
use App\Notifications\PengingatSkPerubahan;
use App\Notifications\PerubahanPkptDiajukan;
use App\Notifications\PerubahanPkptDiputuskan;
use App\Services\PenerimaNotifikasi;
use Carbon\CarbonImmutable;
use Database\Factories\PkptPerubahanItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fase 9.2 Task 6: Perubahan PKPT notifications (E5) and the daily reminders. */
class NotifikasiPerubahanPkptTest extends TestCase
{
    use RefreshDatabase;

    private User $pembuat;

    private User $adminLain;

    private User $pemutus;

    private User $pimpinanLain;

    private User $irbanA;

    private User $irbanB;

    private User $irbanLain;

    private PkptPerubahan $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00', 'Asia/Jakarta'));
        $this->pembuat = User::factory()->admin()->create();
        $this->adminLain = User::factory()->admin()->create();
        $this->pemutus = User::factory()->pimpinan()->create();
        $this->pimpinanLain = User::factory()->pimpinan()->create();
        $a = Irban::factory()->create();
        $b = Irban::factory()->create();
        $this->irbanA = User::factory()->irban()->create(['irban_id' => $a->id]);
        $this->irbanB = User::factory()->irban()->create(['irban_id' => $b->id]);
        $this->irbanLain = User::factory()->irban()->create(['irban_id' => Irban::factory()->create()->id]);

        // One operation moves an item of irban A to irban B: both are affected.
        $item = PkptItem::factory()->create(['irban_id' => $a->id, 'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20',
            'pkpt_id' => Pkpt::factory()->disahkan()->create(['tahun' => 2026])->id]);
        $this->p = PkptPerubahan::factory()->create(['pkpt_id' => $item->pkpt_id, 'dibuat_oleh' => $this->pembuat->id]);
        PkptPerubahanItem::factory()->create(['perubahan_id' => $this->p->id, 'pkpt_item_id' => $item->id,
            'data_baru' => PkptPerubahanItemFactory::data($item, ['irban_id' => $b->id])]);
    }

    private function ajukan(): PkptPerubahan
    {
        return $this->p = app(AjukanPerubahan::class)->handle($this->pembuat, $this->p);
    }

    public function test_diajukan_goes_to_every_pimpinan(): void
    {
        Notification::fake();
        $this->ajukan();
        Notification::assertSentTo([$this->pemutus, $this->pimpinanLain], PerubahanPkptDiajukan::class,
            fn (PerubahanPkptDiajukan $n, $ch, $u) => $n->toArray($u)['url'] === "/pimpinan/perubahan-pkpt/{$this->p->id}" && $n->konteks() === 'PKPT 2026');
        Notification::assertNotSentTo([$this->pembuat, $this->irbanA], PerubahanPkptDiajukan::class);
    }

    public function test_disetujui_recipients_and_url_per_role(): void
    {
        $this->ajukan();
        Notification::fake();
        app(PutuskanPerubahan::class)->handle($this->pemutus, $this->p, true, null);

        $url = fn (User $u) => Notification::sent($u, PerubahanPkptDiputuskan::class)->sole()->toArray($u);
        $this->assertSame(['perubahan_pkpt_disetujui', 'PKPT 2026 · Perubahan ke-1', "/admin/pkpt/{$this->p->pkpt_id}/perubahan/{$this->p->id}"],
            array_values(array_intersect_key($url($this->pembuat), array_flip(['jenis', 'konteks', 'url']))));
        $this->assertSame("/pimpinan/perubahan-pkpt/{$this->p->id}", $url($this->pimpinanLain)['url']);
        $this->assertSame(route('irban.pkpt', absolute: false), $url($this->irbanA)['url']);
        $this->assertSame(route('irban.pkpt', absolute: false), $url($this->irbanB)['url']);
        Notification::assertNotSentTo([$this->pemutus, $this->irbanLain, $this->adminLain], PerubahanPkptDiputuskan::class);
    }

    public function test_ditolak_goes_to_the_creator_or_every_admin_when_the_creator_is_no_longer_admin(): void
    {
        $this->ajukan();
        Notification::fake();
        app(PutuskanPerubahan::class)->handle($this->pemutus, $this->p, false, 'Jadwal bentrok.');
        Notification::assertSentTo($this->pembuat, PerubahanPkptDiputuskan::class,
            fn ($n, $ch, $u) => $n->toArray($u)['jenis'] === 'perubahan_pkpt_ditolak' && $n->toArray($u)['catatan'] === 'Jadwal bentrok.');
        Notification::assertSentTimes(PerubahanPkptDiputuskan::class, 1);

        $this->pembuat->forceFill(['role' => 'pimpinan'])->save();
        $admin = User::where('role', 'admin')->pluck('id')->all();
        $this->assertContains($this->adminLain->id, $admin);
        $this->assertSame($admin, app(PenerimaNotifikasi::class)->pembuatPerubahan($this->p)->pluck('id')->all());
    }

    public function test_pimpinan_reminder_once_a_day_from_the_day_after_submission(): void
    {
        $this->ajukan();
        Notification::fake();
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertNothingSent(); // submitted today

        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00', 'Asia/Jakarta'));
        $this->artisan('notifikasi:harian')
            ->expectsOutput('Terlambat: 0 notifikasi, pengingat SPT final: 0 notifikasi, pengingat perubahan PKPT: 1 notifikasi, pengingat SK perubahan: 0 notifikasi.')
            ->assertSuccessful();
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertSentToTimes($this->pemutus, PengingatPerubahanPkpt::class, 1);
        Notification::assertSentToTimes($this->pimpinanLain, PengingatPerubahanPkpt::class, 1);

        $this->travelTo(CarbonImmutable::parse('2026-10-22 06:00', 'Asia/Jakarta'));
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertSentToTimes($this->pemutus, PengingatPerubahanPkpt::class, 2);
        Notification::assertNotSentTo($this->pembuat, PengingatPerubahanPkpt::class);
    }

    public function test_sk_reminder_to_admins_until_complete_or_turned_off_never_for_migrated(): void
    {
        Storage::fake('local');
        $this->ajukan();
        app(PutuskanPerubahan::class)->handle($this->pemutus, $this->p, true, null);
        $migrasi = PkptPerubahan::factory()->disetujui(9)->create(['pkpt_id' => $this->p->pkpt_id, 'sumber' => 'migrasi_revisi']);
        Notification::fake();

        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertNothingSent(); // approved today

        $this->travelTo(CarbonImmutable::parse('2026-10-21 06:00', 'Asia/Jakarta'));
        $this->artisan('notifikasi:harian')->assertSuccessful();
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertSentToTimes($this->pembuat, PengingatSkPerubahan::class, 1);
        Notification::assertSentToTimes($this->adminLain, PengingatSkPerubahan::class, 1);
        $admin = User::where('role', 'admin')->count(); // every Admin, including the factory-made ones
        Notification::assertSentTimes(PengingatSkPerubahan::class, $admin);
        $this->assertNull($migrasi->fresh()->pengingat_terakhir_pada);

        // Turned off: nothing; back on: again the next day.
        app(AturPengingatSk::class)->handle($this->pembuat, $this->p, false);
        $this->travelTo(CarbonImmutable::parse('2026-10-22 06:00', 'Asia/Jakarta'));
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertSentTimes(PengingatSkPerubahan::class, $admin);
        app(AturPengingatSk::class)->handle($this->pembuat, $this->p, true);
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertSentTimes(PengingatSkPerubahan::class, 2 * $admin);

        // Complete SK: stops.
        app(SimpanSkPerubahan::class)->handle($this->pembuat, $this->p, ['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-10-22'],
            UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf'));
        $this->travelTo(CarbonImmutable::parse('2026-10-23 06:00', 'Asia/Jakarta'));
        $this->artisan('notifikasi:harian')->assertSuccessful();
        Notification::assertSentTimes(PengingatSkPerubahan::class, 2 * $admin);
    }

    public function test_nothing_is_queued_when_the_surrounding_transaction_rolls_back_and_it_is_after_commit(): void
    {
        // Real delivery (sync queue, array mailer): the bell rows would roll back anyway, so the emails are the signal.
        $terkirim = fn () => count(app('mailer')->getSymfonyTransport()->messages());
        try {
            DB::transaction(function () {
                $this->ajukan();
                throw new \RuntimeException('batal');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, $terkirim());
        $this->assertSame('draf', $this->p->fresh()->status->value);

        DB::transaction(function () use ($terkirim) {
            $this->ajukan();
            $this->assertSame(0, $terkirim()); // still inside the transaction
        });
        $this->assertSame(2, $terkirim()); // both Pimpinan
        $this->assertSame(2, DB::table('notifications')->where('data->jenis', 'perubahan_pkpt_diajukan')->count());
    }
}
