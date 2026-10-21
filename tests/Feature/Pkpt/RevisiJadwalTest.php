<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\AjukanRevisiJadwal;
use App\Actions\Pkpt\PutuskanRevisiJadwal;
use App\Enums\Role;
use App\Enums\StatusRevisi;
use App\Livewire\Irban\Pkpt as IrbanPage;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;
use App\Services\KesesuaianPkpt;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RevisiJadwalTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private User $user;

    private Pkpt $pkpt;

    private PkptItem $item;

    private Pegawai $ppj;

    private Pegawai $anggota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Asia/Jakarta'));

        $this->irban = Irban::factory()->create(['nama' => 'Irban I']);
        $this->user = $this->irbanUser($this->irban);
        $this->pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $this->item = PkptItem::factory()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => $this->irban->id,
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20', 'keterangan' => 'Item milik Irban I']);
        $this->ppj = Pegawai::factory()->create(['nama_bergelar' => 'Andi Saputra', 'nip' => '198001012005011001']);
        $this->anggota = Pegawai::factory()->create(['nama_bergelar' => 'Budi Santoso', 'nip' => '199002022015021012']);
        $komposisi = IrbanTahunan::factory()->create(['irban_id' => $this->irban->id, 'tahun' => 2026, 'ppj_pegawai_id' => $this->ppj->id]);
        $komposisi->anggota()->attach($this->anggota->id);
    }

    private function irbanUser(Irban $irban): User
    {
        $u = User::factory()->irban()->create();
        $u->forceFill(['irban_id' => $irban->id])->save();

        return $u;
    }

    private function data(array $over = []): array
    {
        return array_merge(['tanggal_mulai' => '2026-04-06', 'tanggal_selesai' => '2026-04-24', 'alasan' => 'Bentrok dengan audit khusus.', 'diinput_oleh' => $this->anggota->id], $over);
    }

    private function ajukan(array $over = [], ?PkptItem $item = null, ?User $user = null): PkptItemRevisi
    {
        return app(AjukanRevisiJadwal::class)->handle($user ?? $this->user, $item ?? $this->item, $this->data($over));
    }

    private function assertRejected(callable $fn, string $key, string $message): void
    {
        try {
            $fn();
            $this->fail("Expected a validation error on {$key}.");
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()[$key][0] ?? null, json_encode($e->errors()));
        }
    }

    public function test_policy_matrix(): void
    {
        $other = $this->irbanUser(Irban::factory()->create());
        $pimpinan = User::factory()->pimpinan()->create();
        $admin = User::factory()->admin()->create();
        $umum = User::factory()->bagianUmum()->create();
        $revisi = PkptItemRevisi::factory()->create(['pkpt_item_id' => $this->item->id]);
        $drafItem = PkptItem::factory()->create(['pkpt_id' => Pkpt::factory()->create(['tahun' => 2027])->id, 'irban_id' => $this->irban->id,
            'tanggal_mulai' => '2027-03-02', 'tanggal_selesai' => '2027-03-20']);

        $create = fn (User $u, PkptItem $i) => Gate::forUser($u)->allows('create', [PkptItemRevisi::class, $i]);
        $this->assertTrue($create($this->user, $this->item));
        $this->assertFalse($create($this->user, $drafItem));
        foreach ([$other, $pimpinan, $admin, $umum] as $u) {
            $this->assertFalse($create($u, $this->item));
        }

        $this->assertTrue(Gate::forUser($pimpinan)->allows('decide', $revisi));
        foreach ([$this->user, $admin, $umum] as $u) {
            $this->assertFalse(Gate::forUser($u)->allows('decide', $revisi));
        }

        foreach ([$this->user, $pimpinan, $admin] as $u) {
            $this->assertTrue(Gate::forUser($u)->allows('viewAny', PkptItemRevisi::class));
        }
        $this->assertFalse(Gate::forUser($umum)->allows('viewAny', PkptItemRevisi::class));
    }

    public function test_submit_stores_snapshot_and_people(): void
    {
        $r = $this->ajukan();

        $this->assertSame(StatusRevisi::Menunggu, $r->fresh()->status);
        $this->assertSame('2026-03-02', $r->tanggal_mulai_semula->toDateString());
        $this->assertSame('2026-03-20', $r->tanggal_selesai_semula->toDateString());
        $this->assertSame('2026-04-06', $r->tanggal_mulai->toDateString());
        $this->assertSame('2026-04-24', $r->tanggal_selesai->toDateString());
        $this->assertSame($this->user->id, $r->diajukan_oleh);
        $this->assertSame($this->anggota->id, $r->diinput_oleh);
        $this->assertSame('Bentrok dengan audit khusus.', $r->alasan);
        // The item's own columns never change.
        $this->assertSame('2026-03-02', $this->item->fresh()->tanggal_mulai->toDateString());
    }

    public function test_snapshot_is_the_effective_range_after_an_approved_revision(): void
    {
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $this->item->id, 'tanggal_mulai' => '2026-05-04', 'tanggal_selesai' => '2026-05-22']);
        $r = $this->ajukan(['diinput_oleh' => $this->ppj->id]);

        $this->assertSame('2026-05-04', $r->tanggal_mulai_semula->toDateString());
        $this->assertSame('2026-05-22', $r->tanggal_selesai_semula->toDateString());
        $r->delete();
        $this->assertRejected(fn () => $this->ajukan(['tanggal_mulai' => '2026-05-04', 'tanggal_selesai' => '2026-05-22']),
            'tanggal_mulai', 'Tanggal baru sama dengan jadwal yang berlaku.');
    }

    public function test_rule_messages(): void
    {
        $this->assertRejected(fn () => $this->ajukan(['tanggal_mulai' => '']), 'tanggal_mulai', 'Tanggal mulai wajib diisi.');
        $this->assertRejected(fn () => $this->ajukan(['tanggal_selesai' => '']), 'tanggal_selesai', 'Tanggal selesai wajib diisi.');
        $this->assertRejected(fn () => $this->ajukan(['tanggal_mulai' => '2026-05-10', 'tanggal_selesai' => '2026-05-01']), 'tanggal_selesai', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        $this->assertRejected(fn () => $this->ajukan(['tanggal_selesai' => '2027-01-05']), 'tanggal_selesai', 'Tanggal harus berada di tahun 2026.');
        $this->assertRejected(fn () => $this->ajukan(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']), 'tanggal_mulai', 'Tanggal baru sama dengan jadwal yang berlaku.');
        $this->assertRejected(fn () => $this->ajukan(['alasan' => '  ']), 'alasan', 'Isi alasan revisi.');
        $this->assertRejected(fn () => $this->ajukan(['alasan' => str_repeat('a', 1001)]), 'alasan', 'Alasan revisi maksimal 1000 karakter.');
        $this->assertRejected(fn () => $this->ajukan(['diinput_oleh' => null]), 'diinput_oleh', 'Pilih nama penginput terlebih dahulu');

        $draf = Pkpt::factory()->create(['tahun' => 2027]);
        $drafItem = PkptItem::factory()->create(['pkpt_id' => $draf->id, 'irban_id' => $this->irban->id, 'tanggal_mulai' => '2027-03-02', 'tanggal_selesai' => '2027-03-20']);
        $this->assertRejected(fn () => $this->ajukan([], $drafItem), 'pkpt', 'Revisi hanya untuk PKPT yang sudah disahkan.');
        $this->assertSame(0, PkptItemRevisi::count());
    }

    public function test_second_pending_revision_is_rejected(): void
    {
        $this->ajukan();
        $this->assertRejected(fn () => $this->ajukan(['tanggal_mulai' => '2026-07-06', 'tanggal_selesai' => '2026-07-24']), 'revisi', 'Item ini masih punya revisi yang menunggu persetujuan.');
        $this->assertSame(1, PkptItemRevisi::count());
    }

    public function test_diinput_oleh_must_be_active_member_of_this_irban_this_year(): void
    {
        $msg = 'Penginput harus PPJ atau anggota aktif Irban ini tahun 2026.';
        $luar = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => Irban::factory()->create()->id, 'tahun' => 2026, 'ppj_pegawai_id' => $luar->id]);
        $this->assertRejected(fn () => $this->ajukan(['diinput_oleh' => $luar->id]), 'diinput_oleh', $msg);

        $lama = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $this->irban->id, 'tahun' => 2025, 'ppj_pegawai_id' => $lama->id]);
        $this->assertRejected(fn () => $this->ajukan(['diinput_oleh' => $lama->id]), 'diinput_oleh', $msg);

        $this->anggota->update(['aktif' => false]);
        $this->assertRejected(fn () => $this->ajukan(), 'diinput_oleh', $msg);

        $this->assertNotNull($this->ajukan(['diinput_oleh' => $this->ppj->id])->id);
    }

    public function test_other_irban_cannot_submit(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->ajukan([], null, $this->irbanUser(Irban::factory()->create()));
    }

    public function test_decide_setujui_and_tolak(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();
        $act = app(PutuskanRevisiJadwal::class);

        $r = $this->ajukan();
        $act->handle($pimpinan, $r, true, null);
        $r->refresh();
        $this->assertSame(StatusRevisi::Disetujui, $r->status);
        $this->assertSame($pimpinan->id, $r->diputuskan_oleh);
        $this->assertNotNull($r->diputuskan_pada);
        $this->assertNull($r->komentar);
        $this->assertSame('2026-04-06', app(KesesuaianPkpt::class)->rentang($this->item)[0]->toDateString());
        $this->assertRejected(fn () => $act->handle($pimpinan, $r, false, 'Terlambat.'), 'revisi', 'Revisi ini sudah diputuskan.');

        $r2 = $this->ajukan(['tanggal_mulai' => '2026-07-06', 'tanggal_selesai' => '2026-07-24']);
        $this->assertRejected(fn () => $act->handle($pimpinan, $r2, false, ' '), 'komentar', 'Alasan penolakan wajib diisi.');
        $act->handle($pimpinan, $r2, false, 'Jadwal bentrok.');
        $this->assertSame(StatusRevisi::Ditolak, $r2->fresh()->status);
        $this->assertSame('Jadwal bentrok.', $r2->fresh()->komentar);

        $r3 = $this->ajukan(['tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-21']);
        $act->handle($pimpinan, $r3, true, 'Setuju.');
        $this->assertSame('Setuju.', $r3->fresh()->komentar);
    }

    public function test_non_pimpinan_cannot_decide(): void
    {
        $r = $this->ajukan();
        $this->expectException(AuthorizationException::class);
        app(PutuskanRevisiJadwal::class)->handle(User::factory()->admin()->create(), $r, true, null);
    }

    public function test_page_shows_only_own_irban_and_nav_item(): void
    {
        PkptItem::factory()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => Irban::factory()->create()->id, 'keterangan' => 'Item irban lain']);
        $this->actingAs($this->user)->get('/irban/pkpt')->assertOk()
            ->assertSee('Jadwal PKPT')->assertSee('Item milik Irban I')->assertDontSee('Item irban lain')
            ->assertSee('href="'.route('irban.pkpt').'"', false);
        $this->assertContains('irban.pkpt', array_column(Navigation::links(Role::Irban), 'route'));
    }

    public function test_other_roles_get_403(): void
    {
        $this->get('/irban/pkpt')->assertRedirect('/masuk');
        foreach (['admin', 'pimpinan', 'bagianUmum'] as $state) {
            $u = User::factory()->{$state}()->create();
            $this->actingAs($u)->get('/irban/pkpt')->assertForbidden();
            Livewire::actingAs($u)->test(IrbanPage::class)->assertForbidden();
        }
        // Actions re-check (the Livewire update endpoint skips route middleware).
        $page = Livewire::actingAs($this->user)->test(IrbanPage::class);
        $this->actingAs(User::factory()->admin()->create());
        $page->call('buka', $this->item->id)->assertForbidden();
    }

    public function test_cannot_open_item_of_another_irban(): void
    {
        $lain = PkptItem::factory()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => Irban::factory()->create()->id]);
        Livewire::actingAs($this->user)->test(IrbanPage::class)->call('buka', $lain->id)->assertNotFound();
    }

    public function test_empty_states(): void
    {
        $page = Livewire::actingAs($this->user)->test(IrbanPage::class);
        $page->set('tahun', '2027')->assertSee('Belum ada PKPT tahun 2027.');
        Pkpt::factory()->create(['tahun' => 2027]);
        $page->set('tahun', '2025')->set('tahun', '2027')->assertSee('PKPT tahun 2027 belum disahkan.');
        $this->item->delete();
        $page->set('tahun', '2026')->assertSee('Tidak ada item PKPT untuk Irban I tahun 2026.');
    }

    public function test_modal_submit_errors_success_and_session_prefill(): void
    {
        $item2 = PkptItem::factory()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => $this->irban->id, 'tanggal_mulai' => '2026-09-07', 'tanggal_selesai' => '2026-09-25']);
        $key = "diinput_oleh.{$this->irban->id}";

        $page = Livewire::actingAs($this->user)->withQueryParams(['tampilan' => 'tabel'])->test(IrbanPage::class)
            ->call('buka', $this->item->id)
            ->assertSet('tanggal_mulai', '2026-03-02')->assertSet('diinput_oleh', null)
            ->assertSee('Budi Santoso · …1012')
            ->set('tanggal_mulai', '2026-04-06')->set('tanggal_selesai', '2026-04-24')
            ->call('kirim')
            ->assertHasErrors(['alasan', 'diinput_oleh'])->assertSee('Pilih nama penginput terlebih dahulu')->assertSee('Periksa 2 isian berikut');
        $this->assertNull(session($key));
        $this->assertSame(0, PkptItemRevisi::count());

        $page->set('alasan', 'Bentrok.')->set('diinput_oleh', $this->anggota->id)
            ->assertSee('Ajukan sebagai Budi Santoso')
            ->call('kirim')->assertHasNoErrors()->assertSet('open', false)->assertDispatched('toast');
        $this->assertSame($this->anggota->id, session($key));
        $r = PkptItemRevisi::sole();
        $this->assertSame($this->anggota->id, $r->diinput_oleh);

        // Pending item: tag + disabled action with a reason.
        $page->assertSee('Menunggu Persetujuan')->assertSee('Masih ada revisi yang menunggu persetujuan.');
        $page->call('buka', $this->item->id)->assertSet('open', false)->assertDispatched('toast');

        // Prefill on the next write: chip with "Ganti".
        $page->call('buka', $item2->id)->assertSet('diinput_oleh', $this->anggota->id)
            ->assertSee('Ganti penginput, saat ini Budi Santoso')->assertSee('Ajukan sebagai Budi Santoso')
            ->call('gantiPenginput')->assertSet('ganti', true);
    }

    public function test_alert_when_no_komposisi_for_current_year(): void
    {
        IrbanTahunan::query()->delete();
        Livewire::actingAs($this->user)->test(IrbanPage::class)->call('buka', $this->item->id)
            ->assertSee('Komposisi Irban Tahunan');
    }

    public function test_history_disclosure_on_irban_page(): void
    {
        PkptItemRevisi::factory()->ditolak()->create(['pkpt_item_id' => $this->item->id, 'diinput_oleh' => $this->anggota->id, 'komentar' => 'Tidak cukup alasan.']);
        $this->actingAs($this->user)->get('/irban/pkpt?tampilan=tabel')->assertOk()
            ->assertSee('Riwayat revisi (1)')->assertSee('Tidak cukup alasan.')->assertSee('Diinput oleh')->assertSee('Budi Santoso')->assertSee('Ditolak');
    }
}
