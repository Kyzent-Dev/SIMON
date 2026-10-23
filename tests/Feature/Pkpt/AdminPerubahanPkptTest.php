<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\Perubahan\BuatPerubahan;
use App\Enums\Role;
use App\Enums\StatusPerubahan;
use App\Livewire\Admin\Dasbor;
use App\Livewire\Admin\PerubahanPkpt as Halaman;
use App\Livewire\Admin\PkptDetail;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Models\User;
use App\Services\PerubahanPkpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 9.2 Task 7: Admin Perubahan PKPT screens (tabs on a Disahkan PKPT, paket page, dasbor cards). */
class AdminPerubahanPkptTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pkpt $pkpt;

    private Irban $irban1;

    private Irban $irban2;

    private JenisPenugasan $jenis;

    private PkptItem $a;

    private PkptItem $b;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Notification::fake();
        $this->admin = User::factory()->admin()->create();
        $this->pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $this->irban1 = Irban::factory()->create(['nama' => 'Irban I']);
        $this->irban2 = Irban::factory()->create(['nama' => 'Irban II']);
        $this->jenis = JenisPenugasan::factory()->create(['nama' => 'Reviu Laporan Keuangan']);
        $item = fn (array $a) => PkptItem::factory()->disahkanDari()->create([
            'pkpt_id' => $this->pkpt->id, 'irban_id' => $this->irban1->id, 'jenis_penugasan_id' => $this->jenis->id] + $a);
        $this->a = $item(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20', 'keterangan' => 'Item A']);
        $this->b = $item(['tanggal_mulai' => '2026-05-04', 'tanggal_selesai' => '2026-05-15', 'keterangan' => 'Item B']);
    }

    private function paket(array $attr = [], ?string $state = null): PkptPerubahan
    {
        $f = PkptPerubahan::factory();
        if ($state) {
            $f = $f->{$state}();
        }

        return $f->create(['pkpt_id' => $this->pkpt->id, 'dibuat_oleh' => $this->admin->id] + $attr);
    }

    private function halaman(PkptPerubahan $p, ?User $u = null)
    {
        return Livewire::actingAs($u ?? $this->admin)->test(Halaman::class, ['pkpt' => $this->pkpt, 'perubahan' => $p]);
    }

    public function test_tabs_render_for_disahkan_only(): void
    {
        $draf = Pkpt::factory()->create(['tahun' => 2031]);
        Livewire::actingAs($this->admin)->test(PkptDetail::class, ['pkpt' => $draf])
            ->assertDontSee('role="tablist"', false)->assertDontSee('Buat Perubahan')->assertSee('Sahkan PKPT');

        Livewire::actingAs($this->admin)->test(PkptDetail::class, ['pkpt' => $this->pkpt])
            ->assertSee('role="tablist"', false)->assertSee('Tampilkan yang dihapus')->assertSee('Item A')->assertDontSee('Buat Perubahan')
            ->set('tab', 'perubahan')->assertSee('Buat Perubahan')->assertSee('Belum ada Perubahan PKPT.')->assertDontSee('Item A')
            ->set('tab', 'lain')->assertViewHas('tabAktif', 'item')->assertSee('Item A');

        $this->actingAs($this->admin)->get(route('admin.pkpt.show', ['pkpt' => $this->pkpt, 'tab' => 'perubahan']))->assertOk()->assertSee('Buat Perubahan');
    }

    public function test_tampilkan_yang_dihapus_toggles_a_dihapus_item(): void
    {
        PkptItem::factory()->dihapus()->create(['pkpt_id' => $this->pkpt->id, 'irban_id' => $this->irban2->id,
            'jenis_penugasan_id' => $this->jenis->id, 'keterangan' => 'Item terhapus']);

        Livewire::actingAs($this->admin)->test(PkptDetail::class, ['pkpt' => $this->pkpt])
            ->assertDontSee('Item terhapus')->assertSee('2 item')
            ->set('dihapus', true)->assertSee('Item terhapus')->assertSee('Dihapus')->assertSee('1 dihapus');
    }

    public function test_paket_list_and_buat_disabled_while_an_open_paket_exists(): void
    {
        $lama = $this->paket(['sk_lengkap_pada' => null], 'disetujui');
        $lama->operasi()->create(['aksi' => 'hapus', 'pkpt_item_id' => $this->b->id, 'data_baru' => [], 'urutan' => 1]);
        $h = Livewire::actingAs($this->admin)->test(PkptDetail::class, ['pkpt' => $this->pkpt])->set('tab', 'perubahan')
            ->assertSee('Perubahan ke-1')->assertSee('SK belum dilengkapi')->assertDontSee(BuatPerubahan::SUDAH_ADA);
        $this->assertSame(1, $h->viewData('paket')->first()->operasi_count);

        $h->set('buatOpen', true)->set('buat.jenis', 'koreksi_input')->set('buat.alasan', '')->call('buatPerubahan')->assertHasErrors('buat.alasan');
        $h->set('buat.alasan', 'Salah input tanggal.')->call('buatPerubahan');
        $baru = PkptPerubahan::where('status', StatusPerubahan::Draf)->sole();
        $h->assertRedirect(route('admin.pkpt.perubahan', [$this->pkpt, $baru]));
        $this->assertSame('koreksi_input', $baru->jenis->value);

        Livewire::actingAs($this->admin)->test(PkptDetail::class, ['pkpt' => $this->pkpt])->set('tab', 'perubahan')
            ->assertSee(BuatPerubahan::SUDAH_ADA)->assertSee('aria-describedby="alasan-buat-nonaktif"', false)->assertSee('Belum bernomor')
            ->set('buat.alasan', 'Lagi.')->call('buatPerubahan')->assertDispatched('toast', type: 'error', message: BuatPerubahan::SUDAH_ADA);
        $this->assertSame(2, PkptPerubahan::count());
    }

    public function test_full_flow_through_the_component(): void
    {
        Storage::fake('local');
        $p = app(BuatPerubahan::class)->handle($this->admin, $this->pkpt, ['jenis' => 'perubahan', 'alasan' => 'Menyesuaikan jadwal.']);
        $h = $this->halaman($p)->assertSee(PerubahanPkpt::KOSONG)->assertSee('Belum dapat diajukan')->assertSee('alasan-ajukan-nonaktif');

        // tambah
        $h->call('bukaOperasi', 'tambah')->assertSet('opOpen', true)
            ->set('irban_id', (string) $this->irban2->id)->set('jenis_penugasan_id', (string) $this->jenis->id)
            ->set('tanggal_mulai', '2026-07-01')->set('tanggal_selesai', '2026-07-10')->set('target_spt', '2')
            ->call('simpanOperasi')->assertHasNoErrors()->assertSet('opOpen', false)->assertDispatched('fokus-ke', id: 'judul-operasi');

        // ubah: picking the item fills its current values; an unchanged item stays in the modal as an error
        $h->call('bukaOperasi', 'ubah')->assertViewHas('itemOptions', fn ($o) => isset($o[$this->a->id]['warna']) && str_contains($o[$this->a->id]['label'], 'Irban I'))
            ->set('pkpt_item_id', $this->a->id)->assertSet('tanggal_mulai', '2026-03-02')->assertSet('keterangan', 'Item A')
            ->call('simpanOperasi')->assertHasErrors(['operasi'])->assertSet('opOpen', true)
            ->set('tanggal_mulai', '2026-04-01')->set('tanggal_selesai', '2026-04-10')->call('simpanOperasi')->assertHasNoErrors();

        // hapus: item a is already in the paket, so it is not offered again
        $h->call('bukaOperasi', 'hapus')->assertViewHas('itemOptions', fn ($o) => ! isset($o[$this->a->id]) && isset($o[$this->b->id]))
            ->set('pkpt_item_id', $this->b->id)->assertDontSee(PerubahanPkpt::K7_HAPUS)->call('simpanOperasi')->assertHasNoErrors();
        $this->assertSame(['tambah', 'ubah', 'hapus'], $p->operasi()->pluck('aksi')->map->value->all());
        $h->assertSee('Siap diajukan')->assertSee('Sebelum')->assertSee('Sesudah')->assertSee('Isi item baru');

        // an SPT made later on item b: the strip shows cek()'s K7 message
        Spt::factory()->create(['irban_id' => $this->irban1->id, 'pkpt_item_id' => $this->b->id]);
        $h->call('$refresh')->assertSee('Belum dapat diajukan')->assertSee(PerubahanPkpt::K7_HAPUS);
        $hapusB = $p->operasi()->where('aksi', 'hapus')->sole();
        $h->call('konfirmasiHapusOperasi', $hapusB->id)->assertSet('hapusOpen', true)->call('hapusOperasi')
            ->assertDispatched('fokus-ke', id: 'judul-operasi')->assertSee('Siap diajukan');

        // hapus with an item that has an SPT shows the K7 message before submit
        $h->call('bukaOperasi', 'hapus')->set('pkpt_item_id', $this->b->id)->assertSee(PerubahanPkpt::K7_HAPUS)->assertViewHas('pilihanPunyaSpt', true)
            ->set('opOpen', false);

        // SK
        $h->set('nomor_sk', '800/12/2026')->set('tanggal_sk', '2026-06-01')
            ->set('berkas_sk', UploadedFile::fake()->create('sk.pdf', 20, 'application/pdf'))->call('simpanSk')->assertHasNoErrors();
        $p->refresh();
        $this->assertTrue($p->skLengkap());
        Storage::disk('local')->assertExists($p->berkas_sk);
        $h->assertSee('SK lengkap')->assertSee(route('perubahan-pkpt.sk', $p), false);
        $h->set('tanggal_sk', '2026-02-31')->call('simpanSk')->assertHasErrors(['tanggal_sk']);

        // ajukan
        $h->set('tanggal_sk', '2026-06-01')->call('ajukan')->assertHasNoErrors()->assertDispatched('fokus-ke', id: 'judul-perubahan')
            ->assertDontSee('Ajukan ke Pimpinan')->assertSee('Menunggu keputusan Pimpinan');
        $this->assertSame(StatusPerubahan::Diajukan, $p->fresh()->status);
    }

    public function test_stale_tab_shows_a_toast_and_focuses_the_h1(): void
    {
        $p = $this->paket();
        $h = $this->halaman($p)->call('bukaOperasi', 'hapus')->set('pkpt_item_id', $this->a->id);
        $p->forceFill(['status' => StatusPerubahan::Diajukan, 'diajukan_pada' => now()])->save();
        $h->call('simpanOperasi')->assertDispatched('toast', type: 'error', message: BuatPerubahan::BASI)
            ->assertDispatched('fokus-ke', id: 'judul-perubahan')->assertSet('opOpen', false);
        $this->assertSame(0, $p->operasi()->count());
    }

    public function test_rejected_paket_shows_komentar_above_the_form_and_is_editable(): void
    {
        $p = $this->paket(['komentar_putusan' => 'Jadwal bentrok dengan audit BPK.'], 'ditolak');
        $this->halaman($p)->assertSeeInOrder(['Ditolak Pimpinan', 'Jadwal bentrok dengan audit BPK.', 'Jenis dan alasan', 'Tambah item'])
            ->set('alasan', 'Diperbaiki setelah ditolak.')->call('simpanForm')->assertHasNoErrors();
        $this->assertSame('Diperbaiki setelah ditolak.', $p->fresh()->alasan);
        $this->assertSame(StatusPerubahan::Ditolak, $p->fresh()->status);
    }

    public function test_buang_deletes_the_paket_and_returns_to_the_list(): void
    {
        $p = $this->paket();
        $this->halaman($p)->set('buangOpen', true)->call('buang')
            ->assertRedirect(route('admin.pkpt.show', ['pkpt' => $this->pkpt, 'tab' => 'perubahan']));
        $this->assertModelMissing($p);
    }

    public function test_pengingat_toggle_writes_riwayat(): void
    {
        $p = $this->paket([], 'disetujui');
        $h = $this->halaman($p)->assertSee('Matikan pengingat SK')->assertDontSee('Tambah item')->assertDontSee('Buang perubahan')
            ->call('aturPengingat')->assertDispatched('fokus-ke', id: 'judul-perubahan')->assertSee('Nyalakan pengingat SK');
        $this->assertNotNull($p->fresh()->pengingat_sk_dimatikan_pada);
        $h->call('aturPengingat')->assertSee('Matikan pengingat SK');
        $this->assertNull($p->fresh()->pengingat_sk_dimatikan_pada);
        $this->assertSame(['pengingat_dimatikan', 'pengingat_dinyalakan'], $p->riwayat()->orderBy('id')->pluck('kejadian')->all());
        $h->assertSee('Pengingat SK dimatikan')->assertSee($this->admin->name);
    }

    public function test_non_admin_roles_are_forbidden_on_the_route_and_livewire(): void
    {
        $p = $this->paket();
        $url = route('admin.pkpt.perubahan', [$this->pkpt, $p]);
        $this->actingAs($this->admin)->get($url)->assertOk()->assertSeeLivewire(Halaman::class);
        foreach ([Role::Pimpinan, Role::Irban, Role::BagianUmum] as $role) {
            $u = User::factory()->create(['role' => $role, 'irban_id' => $role === Role::Irban ? $this->irban1->id : null]);
            $this->actingAs($u)->get($url)->assertForbidden();
            $this->halaman($p, $u)->assertForbidden();
        }

        $lain = Pkpt::factory()->disahkan()->create(['tahun' => 2032]);
        $this->actingAs($this->admin)->get(route('admin.pkpt.perubahan', [$lain, $p]))->assertNotFound();
    }

    public function test_locked_ids_cannot_be_tampered(): void
    {
        $p = $this->paket();
        $h = $this->halaman($p);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $h->set('perubahanId', $this->paket([], 'disetujui')->id);
    }

    public function test_lookups_are_scoped_to_the_paket_and_its_pkpt(): void
    {
        $p = $this->paket();
        $lain = Pkpt::factory()->disahkan()->create(['tahun' => 2032]);
        $asing = PkptItem::factory()->create(['pkpt_id' => $lain->id, 'irban_id' => $this->irban1->id, 'jenis_penugasan_id' => $this->jenis->id]);
        $h = $this->halaman($p)->call('bukaOperasi', 'hapus')->set('pkpt_item_id', $asing->id)->assertViewHas('itemOptions', fn ($o) => ! isset($o[$asing->id]))
            ->call('simpanOperasi')->assertHasErrors(['pkpt_item_id' => PerubahanPkpt::ITEM_TIDAK_ADA]);
        $this->assertSame(0, $p->operasi()->count());

        $paketLain = PkptPerubahan::factory()->create(['pkpt_id' => $lain->id]);
        $opLain = $paketLain->operasi()->create(['aksi' => 'hapus', 'pkpt_item_id' => $asing->id, 'data_baru' => [], 'urutan' => 1]);
        $h->call('bukaOperasi', 'ubah', $opLain->id)->assertNotFound();
        $this->halaman($p)->call('konfirmasiHapusOperasi', $opLain->id)->assertNotFound();
    }

    public function test_dasbor_cards_count_waiting_paket_and_incomplete_sk(): void
    {
        $h = Livewire::actingAs($this->admin)->test(Dasbor::class)->assertSee('Perubahan menunggu persetujuan')->assertSee('SK belum dilengkapi');
        $this->assertSame([0, 0], [$h->viewData('menunggu'), $h->viewData('skBelum')]);

        $lain = Pkpt::factory()->disahkan()->create(['tahun' => 2032]);
        PkptPerubahan::factory()->diajukan()->create(['pkpt_id' => $lain->id, 'diajukan_pada' => now()->subDays(3)]);
        $this->paket(['diajukan_pada' => now()->subDay()], 'diajukan');
        $this->paket([], 'disetujui');                                                         // counts
        $this->paket(['nomor_urut' => 2, 'pengingat_sk_dimatikan_pada' => now()], 'disetujui'); // reminder off
        $this->paket(['nomor_urut' => 3, 'sk_lengkap_pada' => now()], 'disetujui');             // SK complete
        $this->paket(['nomor_urut' => 4, 'sumber' => 'migrasi_revisi'], 'disetujui');          // migrated

        $h = Livewire::actingAs($this->admin)->test(Dasbor::class);
        $this->assertSame([2, 1], [$h->viewData('menunggu'), $h->viewData('skBelum')]);
        $h->assertSee(route('admin.pkpt.show', ['pkpt' => $lain, 'tab' => 'perubahan']), false);
    }
}
