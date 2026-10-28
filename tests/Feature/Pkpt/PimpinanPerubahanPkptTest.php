<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\Perubahan\AjukanPerubahan;
use App\Actions\Pkpt\Perubahan\BuangPerubahan;
use App\Actions\Pkpt\Perubahan\BuatPerubahan;
use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use App\Actions\Pkpt\Perubahan\SimpanOperasi;
use App\Enums\Role;
use App\Enums\StatusItemPkpt;
use App\Enums\StatusPerubahan;
use App\Livewire\Pimpinan\PerubahanPkpt as Daftar;
use App\Livewire\Pimpinan\PerubahanPkptDetail as Detail;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Models\User;
use App\Services\PerubahanPkpt;
use App\Support\Navigation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 9.2 Task 8: Pimpinan Perubahan PKPT screens (list, detail with impact, Setujui/Tolak, access). */
class PimpinanPerubahanPkptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pimpinan;

    private Pkpt $pkpt;

    private PkptItem $a;

    private Spt $spt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->admin = User::factory()->admin()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $irban = Irban::factory()->create(['nama' => 'Irban I']);
        $jenis = JenisPenugasan::factory()->create(['nama' => 'Reviu Laporan Keuangan']);
        $this->a = PkptItem::factory()->disahkanDari()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => $irban->id, 'jenis_penugasan_id' => $jenis->id,
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20', 'keterangan' => 'Item A']);
        $this->spt = Spt::factory()->diterima()->create(['irban_id' => $irban->id, 'pkpt_item_id' => $this->a->id,
            'tanggal_mulai' => '2026-04-06', 'tanggal_selesai' => '2026-04-08']);
    }

    /** A submitted package moving item A so that the accepted SPT flips from Tidak Sesuai PKPT to Sesuai. */
    private function diajukan(): PkptPerubahan
    {
        $p = app(BuatPerubahan::class)->handle($this->admin, $this->pkpt, ['jenis' => 'perubahan', 'alasan' => 'Menggeser jadwal Item A.']);
        app(SimpanOperasi::class)->handle($this->admin, $p, null, ['aksi' => 'ubah', 'pkpt_item_id' => $this->a->id,
            'tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-10']);

        return app(AjukanPerubahan::class)->handle($this->admin, $p);
    }

    public function test_list_shows_menunggu_and_riwayat_and_empty_state(): void
    {
        $this->actingAs($this->pimpinan);
        Livewire::test(Daftar::class)->assertSee('Tidak ada perubahan PKPT yang menunggu persetujuan.')->assertSee('Belum ada perubahan yang diputuskan.');

        $p = $this->diajukan();
        PkptPerubahan::factory()->create(['pkpt_id' => $this->pkpt->id, 'status' => StatusPerubahan::Ditolak, 'diputuskan_pada' => now()]);
        $this->get(route('pimpinan.perubahan-pkpt'))->assertOk()->assertSee('Perubahan PKPT 2026')->assertSee('Menggeser jadwal Item A.')
            ->assertSee('Tinjau')->assertSee(route('pimpinan.perubahan-pkpt.show', $p), false)->assertSee('Ditolak')
            ->assertDontSee('Tidak ada perubahan PKPT yang menunggu persetujuan.');
    }

    public function test_detail_shows_operasi_and_dampak(): void
    {
        $p = $this->diajukan();
        $this->actingAs($this->pimpinan)->get(route('pimpinan.perubahan-pkpt.show', $p))->assertOk()
            ->assertSee('Ubah item')->assertSee('Sebelum')->assertSee('Sesudah')
            ->assertSee('Dampak per irban')->assertSee('Irban I')->assertSee('SPT yang kesesuaiannya berubah')
            ->assertSee($this->spt->nomor_lengkap)->assertSee('Tidak Sesuai PKPT')
            ->assertSee('Setujui Perubahan PKPT 2026', false)->assertSee('Tolak Perubahan PKPT 2026', false);
    }

    public function test_setujui_applies_and_moves_focus(): void
    {
        $p = $this->diajukan();
        $this->actingAs($this->pimpinan);
        Livewire::test(Detail::class, ['perubahan' => $p])->call('setujui')
            ->assertDispatched('fokus-ke', id: 'judul-perubahan')->assertDispatched('toast')
            ->assertDontSee('Keputusan');
        $this->assertSame(StatusPerubahan::Disetujui, $p->fresh()->status);
        $this->assertSame('2026-04-10', $this->a->fresh()->tanggal_selesai->toDateString());
    }

    public function test_tolak_needs_komentar(): void
    {
        $p = $this->diajukan();
        $this->actingAs($this->pimpinan);
        Livewire::test(Detail::class, ['perubahan' => $p])->set('komentar', '  ')->call('tolak')
            ->assertHasErrors(['komentar'])->assertSee('Alasan penolakan wajib diisi.');
        $this->assertSame(StatusPerubahan::Diajukan, $p->fresh()->status);

        Livewire::test(Detail::class, ['perubahan' => $p])->set('komentar', 'Jadwal bentrok.')->call('tolak')
            ->assertHasNoErrors()->assertDispatched('fokus-ke', id: 'judul-perubahan');
        $this->assertSame([StatusPerubahan::Ditolak, 'Jadwal bentrok.'], [$p->fresh()->status, $p->fresh()->komentar_putusan]);
        $this->assertSame(StatusItemPkpt::Aktif, $this->a->fresh()->status);
    }

    public function test_spt_created_after_submission_shows_the_reason_alert_and_status_stays(): void
    {
        $p = app(BuatPerubahan::class)->handle($this->admin, $this->pkpt, ['jenis' => 'perubahan', 'alasan' => 'Hapus item.']);
        $b = PkptItem::factory()->disahkanDari()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => $this->a->irban_id, 'jenis_penugasan_id' => $this->a->jenis_penugasan_id]);
        app(SimpanOperasi::class)->handle($this->admin, $p, null, ['aksi' => 'hapus', 'pkpt_item_id' => $b->id]);
        $p = app(AjukanPerubahan::class)->handle($this->admin, $p);
        Spt::factory()->create(['pkpt_item_id' => $b->id]);

        $this->actingAs($this->pimpinan);
        Livewire::test(Detail::class, ['perubahan' => $p])->call('setujui')
            ->assertSee('Perubahan belum dapat disetujui')->assertSee(PerubahanPkpt::K7_HAPUS)
            ->assertSeeHtml('role="alert"')->assertDispatched('fokus-ke', id: 'alert-gagal')
            ->assertSee('Keputusan');
        $this->assertSame(StatusPerubahan::Diajukan, $p->fresh()->status);
    }

    public function test_stale_decision_is_a_toast(): void
    {
        $p = $this->diajukan();
        $this->actingAs($this->pimpinan);
        $c = Livewire::test(Detail::class, ['perubahan' => $p]);
        app(PutuskanPerubahan::class)->handle($this->pimpinan, $p, false, 'Tidak.');
        $c->call('setujui')->assertDispatched('toast', type: 'error', message: PutuskanPerubahan::SUDAH_DIPUTUSKAN);
    }

    public function test_other_roles_get_403_on_routes_and_direct_actions(): void
    {
        $p = $this->diajukan();
        foreach ([$this->admin, User::factory()->irban()->create(), User::factory()->bagianUmum()->create()] as $user) {
            $this->actingAs($user);
            $this->get(route('pimpinan.perubahan-pkpt'))->assertForbidden();
            $this->get(route('pimpinan.perubahan-pkpt.show', $p))->assertForbidden();
            Livewire::test(Daftar::class)->assertForbidden();
            Livewire::test(Detail::class, ['perubahan' => $p])->assertForbidden();
        }
        // An action called after the role changed mid-session is refused too.
        $this->actingAs($this->pimpinan);
        $c = Livewire::test(Detail::class, ['perubahan' => $p]);
        $this->pimpinan->forceFill(['role' => Role::Admin])->save();
        $c->call('setujui')->assertForbidden();
        $this->assertSame(StatusPerubahan::Diajukan, $p->fresh()->status);

        // Fase 11.2 Task 6 butir 15: Tolak called directly after the role changed, and the action itself, refuse too.
        $this->pimpinan->forceFill(['role' => Role::Pimpinan])->save();
        $c = Livewire::test(Detail::class, ['perubahan' => $p])->set('komentar', 'Tolak lewat panggilan langsung.');
        $this->pimpinan->forceFill(['role' => Role::Admin])->save();
        $c->call('tolak')->assertForbidden();
        try {
            app(PutuskanPerubahan::class)->handle($this->admin, $p, false, 'Tolak oleh Admin.');
            $this->fail('AuthorizationException diharapkan.');
        } catch (AuthorizationException) {
        }
        $p->refresh();
        $this->assertSame([StatusPerubahan::Diajukan, null], [$p->status, $p->komentar_putusan]);
    }

    public function test_nav_item_and_old_route_redirect(): void
    {
        $item = collect(Navigation::groups(Role::Pimpinan))->flatMap(fn ($g) => $g['items'])->firstWhere('label', 'Perubahan PKPT');
        $this->assertSame(['pimpinan.perubahan-pkpt', 'arrow-path', ['pimpinan.perubahan-pkpt.show']], [$item['route'], $item['icon'], $item['aktif']]);
        $this->actingAs($this->pimpinan)->get('/pimpinan/revisi-jadwal')->assertRedirect(route('pimpinan.perubahan-pkpt'));
    }

    /** Fase 11.2 Task 6 butir 12: an empty rejection reason keeps the modal open and moves focus to the textarea. */
    public function test_komentar_tolak_kosong_memfokuskan_textarea(): void
    {
        $p = $this->diajukan();
        $this->actingAs($this->pimpinan);
        Livewire::test(Detail::class, ['perubahan' => $p])->set('tolakOpen', true)->call('tolak')
            ->assertHasErrors('komentar')->assertSet('tolakOpen', true)->assertDispatched('fokus-ke', id: 'komentar')
            ->assertSeeHtml('id="komentar"');
    }

    /** Fase 11.2 Task 6 butir 13: the page size is fixed (no per-page control), so the client cannot raise it. */
    public function test_per_page_tidak_bisa_diubah_klien(): void
    {
        $this->actingAs($this->pimpinan);
        $c = Livewire::test(Daftar::class)->assertSet('perPage', 20);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('perPage', 100000);
    }

    /** Fase 11.2 Task 6 butir 18: the bell link of a package that was discarded later leads to the list with a toast, not a 404. */
    public function test_tautan_ke_paket_yang_dibuang_dialihkan_dengan_toast(): void
    {
        $p = app(BuatPerubahan::class)->handle($this->admin, $this->pkpt, ['jenis' => 'perubahan', 'alasan' => 'Akan dibuang.']);
        $url = route('pimpinan.perubahan-pkpt.show', $p);
        $this->actingAs($this->pimpinan)->get($url)->assertOk(); // control
        app(BuangPerubahan::class)->handle($this->admin, $p);
        $this->get($url)->assertRedirect(route('pimpinan.perubahan-pkpt'))
            ->assertSessionHas('toast', ['type' => 'error', 'message' => BuangPerubahan::DIBUANG]);
    }

    /** Fase 11.3: a draf open in this tab and discarded by Admin turns the next request (re-render or action) into the list with a toast. */
    public function test_paket_dibuang_saat_halaman_terbuka_memberi_toast_bukan_404(): void
    {
        foreach (['$refresh', 'setujui'] as $aksi) {
            $p = app(BuatPerubahan::class)->handle($this->admin, $this->pkpt, ['jenis' => 'perubahan', 'alasan' => 'Akan dibuang.']);
            $c = Livewire::actingAs($this->pimpinan)->test(Detail::class, ['perubahan' => $p]);
            $c->call('$refresh')->assertOk(); // control: still there
            app(BuangPerubahan::class)->handle($this->admin, $p);
            $c->call($aksi)->assertRedirect(route('pimpinan.perubahan-pkpt'));
            $this->assertSame(['type' => 'error', 'message' => BuangPerubahan::DIBUANG], session('toast'));
            session()->forget('toast');
        }
    }
}
