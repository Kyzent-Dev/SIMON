<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\AjukanRevisiJadwal;
use App\Actions\Pkpt\PutuskanRevisiJadwal;
use App\Enums\Role;
use App\Enums\StatusRevisi;
use App\Livewire\Pimpinan\RevisiJadwal;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\JenisPenugasan;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;
use App\Services\KesesuaianPkpt;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PersetujuanRevisiTest extends TestCase
{
    use RefreshDatabase;

    private User $pimpinan;

    private User $irbanUser;

    private PkptItem $item;

    private Pegawai $anggota;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Asia/Jakarta'));

        $irban = Irban::factory()->create(['nama' => 'Irban II']);
        $this->irbanUser = User::factory()->irban()->create(['name' => 'Akun Irban II']);
        $this->irbanUser->forceFill(['irban_id' => $irban->id])->save();
        $this->pimpinan = User::factory()->pimpinan()->create(['name' => 'Inspektur']);
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $jenis = JenisPenugasan::factory()->create(['nama' => 'Audit Kinerja']);
        $this->item = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $irban->id, 'jenis_penugasan_id' => $jenis->id,
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']);
        $this->anggota = Pegawai::factory()->create(['nama_bergelar' => 'Budi Santoso']);
        $komposisi = IrbanTahunan::factory()->create(['irban_id' => $irban->id, 'tahun' => 2026, 'ppj_pegawai_id' => Pegawai::factory()->create()->id]);
        $komposisi->anggota()->attach($this->anggota->id);
    }

    private function ajukan(string $mulai = '2026-04-06', string $selesai = '2026-04-24'): PkptItemRevisi
    {
        return app(AjukanRevisiJadwal::class)->handle($this->irbanUser, $this->item, [
            'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai, 'alasan' => 'Bentrok dengan audit khusus.', 'diinput_oleh' => $this->anggota->id,
        ]);
    }

    private function page()
    {
        return Livewire::actingAs($this->pimpinan)->test(RevisiJadwal::class);
    }

    public function test_route_nav_and_403_for_other_roles(): void
    {
        $this->actingAs($this->pimpinan)->get('/pimpinan/revisi-jadwal')->assertOk()
            ->assertSee('Menunggu Persetujuan Anda')->assertSee('Riwayat keputusan');
        $this->assertContains('pimpinan.revisi-jadwal', array_column(Navigation::links(Role::Pimpinan), 'route'));

        $r = $this->ajukan();
        foreach (['admin', 'irban', 'bagianUmum'] as $state) {
            $u = User::factory()->{$state}()->create();
            $this->actingAs($u)->get('/pimpinan/revisi-jadwal')->assertForbidden();
            Livewire::actingAs($u)->test(RevisiJadwal::class)->assertForbidden();
        }
        // Actions re-check the role (the Livewire update endpoint skips route middleware).
        $admin = User::factory()->admin()->create();
        $page = $this->page();
        $this->actingAs($admin);
        $page->call('setujui', $r->id)->assertForbidden();
        $page = $this->page()->call('setujui', $r->id);
        $this->actingAs($admin);
        $page->call('putuskan', true)->assertForbidden();
        $this->assertSame(StatusRevisi::Menunggu, $r->fresh()->status);
    }

    public function test_list_shows_only_menunggu_with_details_and_accessible_names(): void
    {
        $r = $this->ajukan();
        PkptItemRevisi::factory()->ditolak()->create(['pkpt_item_id' => $this->item->id, 'alasan' => 'Alasan lama yang ditolak.', 'komentar' => 'Komentar lama.']);

        $this->page()
            ->assertSee('Audit Kinerja')->assertSee('Irban II')
            ->assertSee('2–20 Maret 2026')->assertSee('6–24 April 2026')
            ->assertSee('Bentrok dengan audit khusus.')
            ->assertSeeHtml('Diinput oleh <strong class="font-semibold text-neutral-900">Budi Santoso</strong> (akun Akun Irban II)')
            ->assertSeeHtml('aria-label="Setujui revisi jadwal Audit Kinerja Irban II"')
            ->assertSeeHtml('aria-label="Tolak revisi jadwal Audit Kinerja Irban II"')
            ->assertSeeHtml('wire:click="setujui('.$r->id.')"')
            // The rejected one is in the history table, not a card.
            ->assertDontSeeHtml('wire:click="setujui('.($r->id + 1).')"')
            ->assertSee('Komentar lama.');
    }

    public function test_empty_state(): void
    {
        $this->page()->assertSee('Tidak ada revisi jadwal yang menunggu persetujuan.');
    }

    public function test_setujui_and_tolak_through_component(): void
    {
        $r = $this->ajukan();
        $this->page()->call('setujui', $r->id)->assertSet('setujuiOpen', true)
            ->set('komentar', 'Silakan.')->call('putuskan', true)
            ->assertHasNoErrors()->assertSet('setujuiOpen', false)
            ->assertDispatched('revisi-diputuskan')->assertDispatched('toast')
            ->assertSee('Tidak ada revisi jadwal yang menunggu persetujuan.');
        $r->refresh();
        $this->assertSame(StatusRevisi::Disetujui, $r->status);
        $this->assertSame('Silakan.', $r->komentar);
        $this->assertSame($this->pimpinan->id, $r->diputuskan_oleh);

        $r2 = $this->ajukan('2026-07-06', '2026-07-24');
        $page = $this->page()->call('tolak', $r2->id)->assertSet('tolakOpen', true)
            ->call('putuskan', false)->assertHasErrors('komentar')->assertSee('Alasan penolakan wajib diisi.')
            ->assertSet('tolakOpen', true);
        $this->assertSame(StatusRevisi::Menunggu, $r2->fresh()->status);
        $page->set('komentar', str_repeat('a', 1001))->call('putuskan', false)->assertSee('Komentar maksimal 1000 karakter.');
        $page->set('komentar', 'Jadwal bentrok.')->call('putuskan', false)->assertHasNoErrors()->assertSet('tolakOpen', false);
        $this->assertSame(StatusRevisi::Ditolak, $r2->fresh()->status);
        $this->assertSame('Jadwal bentrok.', $r2->fresh()->komentar);
    }

    public function test_already_decided_and_tampered_ids(): void
    {
        $r = $this->ajukan();
        $page = $this->page()->call('setujui', $r->id);
        app(PutuskanRevisiJadwal::class)->handle($this->pimpinan, $r, false, 'Sudah ditolak.');
        $page->call('putuskan', true)->assertSet('setujuiOpen', false)->assertDispatched('toast');
        $this->assertSame(StatusRevisi::Ditolak, $r->fresh()->status);

        $this->page()->call('setujui', 999999)->assertNotFound();
        $this->page()->call('tolak', $r->id)->assertSet('tolakOpen', false)->assertDispatched('toast');
    }

    public function test_komentar_max_length_in_action(): void
    {
        $r = $this->ajukan();
        try {
            app(PutuskanRevisiJadwal::class)->handle($this->pimpinan, $r, true, str_repeat('a', 1001));
            $this->fail('Expected a validation error.');
        } catch (ValidationException $e) {
            $this->assertSame('Komentar maksimal 1000 karakter.', $e->errors()['komentar'][0]);
        }
        $this->assertSame(StatusRevisi::Menunggu, $r->fresh()->status);
    }

    public function test_history_year_filter_and_pagination(): void
    {
        PkptItemRevisi::factory()->ditolak()->create(['pkpt_item_id' => $this->item->id, 'komentar' => 'Keputusan 2026.']);
        $lama = Pkpt::factory()->disahkan()->create(['tahun' => 2025]);
        $itemLama = PkptItem::factory()->create(['pkpt_id' => $lama->id, 'tanggal_mulai' => '2025-03-03', 'tanggal_selesai' => '2025-03-21']);
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $itemLama->id, 'komentar' => 'Keputusan 2025.',
            'tanggal_mulai_semula' => '2025-03-03', 'tanggal_selesai_semula' => '2025-03-21', 'tanggal_mulai' => '2025-04-07', 'tanggal_selesai' => '2025-04-25']);

        $page = $this->page()->assertSet('tahun', '2026')->assertSee('Keputusan 2026.')->assertDontSee('Keputusan 2025.');
        $page->set('tahun', '2025')->assertSee('Keputusan 2025.')->assertDontSee('Keputusan 2026.');
        $page->set('tahun', '2024')->assertSee('Belum ada keputusan revisi jadwal untuk tahun 2024.');

        PkptItemRevisi::factory()->count(26)->disetujui()->create(['pkpt_item_id' => $this->item->id]);
        $this->page()->assertSee('Menampilkan 1–25 dari 27');
    }

    /** PRD Fase 3 acceptance: an approved revision changes the range used for kesesuaian; the original schedule stays stored. */
    public function test_acceptance_approved_revision_changes_kesesuaian_and_keeps_original(): void
    {
        $kesesuaian = app(KesesuaianPkpt::class);
        $this->assertFalse($kesesuaian->sesuai($this->item, '2026-04-08', '2026-04-20'));

        $r = $this->ajukan('2026-04-06', '2026-04-24');
        $this->page()->call('setujui', $r->id)->call('putuskan', true)->assertHasNoErrors();

        $this->assertTrue($kesesuaian->sesuai($this->item, '2026-04-08', '2026-04-20'));
        [$mulai, $selesai] = $kesesuaian->rentang($this->item);
        $this->assertSame(['2026-04-06', '2026-04-24'], [$mulai->toDateString(), $selesai->toDateString()]);
        $asli = $this->item->fresh();
        $this->assertSame(['2026-03-02', '2026-03-20'], [$asli->tanggal_mulai->toDateString(), $asli->tanggal_selesai->toDateString()]);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.pkpt.show', $this->item->pkpt_id))->assertOk()
            ->assertSee('6 April 2026 – 24 April 2026')->assertSee('semula 2–20 Maret 2026');
        $this->actingAs($this->irbanUser)->get('/irban/pkpt?tampilan=tabel')->assertOk()
            ->assertSee('6 April 2026 – 24 April 2026')->assertSee('semula 2–20 Maret 2026');
    }

    public function test_acceptance_rejected_revision_leaves_kesesuaian_unchanged(): void
    {
        $r = $this->ajukan('2026-04-06', '2026-04-24');
        $this->page()->call('tolak', $r->id)->set('komentar', 'Tidak cukup alasan.')->call('putuskan', false)->assertHasNoErrors();

        $kesesuaian = app(KesesuaianPkpt::class);
        $this->assertFalse($kesesuaian->sesuai($this->item, '2026-04-08', '2026-04-20'));
        $this->assertSame('2026-03-02', $kesesuaian->rentang($this->item)[0]->toDateString());
    }
}
