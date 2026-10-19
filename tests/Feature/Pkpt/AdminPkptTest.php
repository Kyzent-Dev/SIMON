<?php

namespace Tests\Feature\Pkpt;

use App\Enums\StatusPkpt;
use App\Livewire\Admin\Pkpt as ListPage;
use App\Livewire\Admin\PkptDetail as DetailPage;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;
use App\Support\Tanggal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPkptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function list()
    {
        return Livewire::actingAs($this->admin())->test(ListPage::class);
    }

    private function detail(Pkpt $pkpt)
    {
        return Livewire::actingAs($this->admin())->test(DetailPage::class, ['pkpt' => $pkpt]);
    }

    private function draf(): Pkpt
    {
        return Pkpt::factory()->create(['tahun' => 2026]);
    }

    public function test_non_admin_gets_403_on_pages_and_actions(): void
    {
        $pkpt = $this->draf();
        $irban = User::factory()->irban()->create();
        $this->get('/admin/pkpt')->assertRedirect('/masuk');
        foreach (['/admin/pkpt', "/admin/pkpt/{$pkpt->id}", "/admin/pkpt/{$pkpt->id}/impor"] as $url) {
            $this->actingAs($irban)->get($url)->assertForbidden();
        }
        Livewire::actingAs($irban)->test(ListPage::class)->assertForbidden();
        Livewire::actingAs($irban)->test(DetailPage::class, ['pkpt' => $pkpt])->assertForbidden();

        // Actions re-check the gate (the Livewire update endpoint skips route middleware).
        $list = $this->list();
        $detail = $this->detail($pkpt);
        $this->actingAs($irban);
        $list->call('create')->assertForbidden();
        $detail->call('tambah')->assertForbidden();
    }

    public function test_admin_pages_render_and_nav_shows_pkpt(): void
    {
        $pkpt = $this->draf();
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/pkpt')->assertOk()
            ->assertSee('href="'.route('admin.pkpt').'"', false)->assertSee('PKPT');
        $this->actingAs($admin)->get("/admin/pkpt/{$pkpt->id}")->assertOk()->assertSee('PKPT 2026');
        $this->actingAs($admin)->get("/admin/pkpt/{$pkpt->id}/impor")->assertOk()->assertSee('Segera tersedia');
    }

    public function test_list_renders_rows_with_tags(): void
    {
        $d = Pkpt::factory()->disahkan()->create(['tahun' => 2026, 'nomor_sk' => '100.3.3.2/1/KPTS/INSPEKTORAT/2026', 'tanggal_sk' => '2026-01-02']);
        Pkpt::factory()->create(['tahun' => 2027]);
        PkptItem::factory()->count(3)->create(['pkpt_id' => $d->id]);

        $this->list()
            ->assertSeeInOrder(['2027', 'Draf', '2026', '100.3.3.2/1/KPTS/INSPEKTORAT/2026', '02 Januari 2026', 'Disahkan', '3'])
            ->assertSee('href="'.route('admin.pkpt.show', $d).'"', false);
    }

    public function test_empty_state(): void
    {
        $this->list()->assertSee('Belum ada PKPT. Tambahkan PKPT tahun ini.');
    }

    public function test_create_edit_delete_pkpt(): void
    {
        $page = $this->list()->call('create')->assertSet('tahun', (string) now()->year)
            ->set('tahun', '2026')->set('nomor_sk', 'SK/1')->set('tanggal_sk', '2026-01-02')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast');
        $pkpt = Pkpt::where('tahun', 2026)->firstOrFail();
        $this->assertSame('SK/1', $pkpt->nomor_sk);

        $page->call('create')->set('tahun', '2026')->call('save')->assertHasErrors(['tahun' => 'PKPT tahun 2026 sudah ada.']);

        $page->call('edit', $pkpt->id)->assertSet('nomor_sk', 'SK/1')->assertSet('tanggal_sk', '2026-01-02')
            ->set('nomor_sk', 'SK/2')->call('save')->assertHasNoErrors();
        $this->assertSame('SK/2', $pkpt->fresh()->nomor_sk);

        $page->call('confirmDelete', $pkpt->id)->assertSet('deleteOpen', true)->call('delete');
        $this->assertModelMissing($pkpt);
    }

    public function test_disahkan_pkpt_has_no_edit_or_delete_on_list(): void
    {
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $this->list()->assertDontSee('aria-label="Ubah PKPT 2026"', false)
            ->call('confirmDelete', $pkpt->id)->call('delete')->assertDispatched('toast', type: 'error');
        $this->assertModelExists($pkpt);
    }

    public function test_saving_pkpt_disahkan_meanwhile_shows_error_toast(): void
    {
        $pkpt = $this->draf();
        $page = $this->list()->call('edit', $pkpt->id);
        $pkpt->forceFill(['status' => StatusPkpt::Disahkan])->save();

        $page->set('nomor_sk', 'SK/9')->call('save')
            ->assertDispatched('toast', type: 'error', message: 'PKPT yang sudah disahkan tidak dapat diubah.');
        $this->assertNull($pkpt->fresh()->nomor_sk);
    }

    public function test_inline_add_edit_delete_item(): void
    {
        $pkpt = $this->draf();
        $irban = Irban::factory()->create(['nama' => 'Irban I']);
        $jenis = JenisPenugasan::factory()->create(['nama' => 'Audit Kinerja']);

        $page = $this->detail($pkpt)
            ->set('baru.irban_id', (string) $irban->id)->set('baru.jenis_penugasan_id', (string) $jenis->id)
            ->set('baru.tanggal_mulai', '2026-03-03')->set('baru.tanggal_selesai', '2026-03-21')
            ->set('baru.keterangan', 'Kec. Lawang Kidul')->set('baru.target_spt', '4')
            ->call('tambah')->assertHasNoErrors()->assertSet('baru.tanggal_mulai', '');
        $item = PkptItem::firstOrFail();
        $this->assertSame(4, $item->target_spt);
        $page->assertSee('Audit Kinerja')->assertSee('3 Maret 2026 – 21 Maret 2026')->assertSee('Durasi: 19 hari');

        $page->call('edit', $item->id)->assertSet('ubahId', $item->id)->assertSet('ubah.tanggal_mulai', '2026-03-03')
            ->set('ubah.tanggal_selesai', '2026-03-31')->call('simpan')->assertHasNoErrors()->assertSet('ubahId', null);
        $this->assertSame('2026-03-31', $item->fresh()->tanggal_selesai->toDateString());

        $page->call('confirmDelete', $item->id)->assertSet('deleteOpen', true)->call('delete');
        $this->assertModelMissing($item);
    }

    public function test_item_validation_messages_are_shown(): void
    {
        $pkpt = $this->draf();
        $this->detail($pkpt)->call('tambah')
            ->assertHasErrors(['baru.irban_id', 'baru.jenis_penugasan_id', 'baru.tanggal_mulai'])
            ->assertSee('Pilih irban.')
            ->set('baru.tanggal_mulai', '2026-05-10')->set('baru.tanggal_selesai', '2026-05-01')->call('tambah')
            ->assertSee('Tanggal selesai tidak boleh sebelum tanggal mulai.')
            ->set('baru.tanggal_mulai', '2025-12-10')->set('baru.tanggal_selesai', '2026-01-05')->call('tambah')
            ->assertSee('Tanggal harus berada di tahun 2026.');
        $this->assertSame(0, PkptItem::count());
    }

    public function test_cannot_edit_item_of_another_pkpt(): void
    {
        $other = PkptItem::factory()->create();
        $this->detail($this->draf())->call('edit', $other->id)->assertNotFound();
    }

    public function test_filter_by_irban(): void
    {
        $pkpt = $this->draf();
        $a = Irban::factory()->create(['nama' => 'Irban I']);
        $b = Irban::factory()->create(['nama' => 'Irban II']);
        PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $a->id, 'keterangan' => 'Item A']);
        PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $b->id, 'keterangan' => 'Item B']);

        $this->detail($pkpt)->assertSee('Item A')->assertSee('Item B')
            ->set('irbanId', (string) $b->id)->assertDontSee('Item A')->assertSee('Item B');
    }

    public function test_sahkan_via_modal(): void
    {
        $pkpt = $this->draf();
        $page = $this->detail($pkpt)->assertSee('Sahkan PKPT')
            ->call('sahkan')->assertDispatched('toast', type: 'error', message: 'Isi nomor dan tanggal SK Bupati sebelum mengesahkan.');
        $this->assertTrue($pkpt->fresh()->isDraf());

        $pkpt->update(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-02']);
        PkptItem::factory()->create(['pkpt_id' => $pkpt->id]);
        $page->set('sahkanOpen', true)->call('sahkan')->assertSet('sahkanOpen', false);
        $this->assertSame(StatusPkpt::Disahkan, $pkpt->fresh()->status);
    }

    public function test_disahkan_page_is_read_only_with_effective_range_and_history(): void
    {
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $item = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'tanggal_mulai' => '2026-02-03', 'tanggal_selesai' => '2026-02-21']);
        $pimpinan = User::factory()->pimpinan()->create(['name' => 'Kepala Inspektur']);
        PkptItemRevisi::factory()->ditolak()->create([
            'pkpt_item_id' => $item->id, 'tanggal_mulai_semula' => '2026-02-03', 'tanggal_selesai_semula' => '2026-02-21',
            'tanggal_mulai' => '2026-05-01', 'tanggal_selesai' => '2026-05-10', 'alasan' => 'Alasan pertama', 'komentar' => 'Terlalu jauh',
        ]);
        PkptItemRevisi::factory()->disetujui()->create([
            'pkpt_item_id' => $item->id, 'tanggal_mulai_semula' => '2026-02-03', 'tanggal_selesai_semula' => '2026-02-21',
            'tanggal_mulai' => '2026-03-03', 'tanggal_selesai' => '2026-03-21', 'alasan' => 'Bentrok jadwal',
            'diputuskan_oleh' => $pimpinan->id, 'diputuskan_pada' => '2026-02-01 10:00:00',
        ]);

        $this->detail($pkpt)
            ->assertDontSee('Sahkan PKPT')->assertDontSee('Impor Excel')->assertDontSee('Tambah')
            ->assertDontSee('wire:click="edit', false)->assertDontSee('wire:click="confirmDelete', false)
            ->assertSee('Jadwal berlaku')
            ->assertSee('3 Maret 2026 – 21 Maret 2026')
            ->assertSee('semula 3–21 Februari 2026')
            ->assertSee('Riwayat revisi')
            ->assertSee('Bentrok jadwal')->assertSee('Alasan pertama')->assertSee('Terlalu jauh')
            ->assertSee('Disetujui')->assertSee('Ditolak')->assertSee('Kepala Inspektur')->assertSee('1 Februari 2026');
    }

    public function test_tag_component_renders_icon_and_label(): void
    {
        $html = Blade::render('<x-app.tag icon="check-badge" variant="mint">Disahkan</x-app.tag>');
        $this->assertStringContainsString('Disahkan', $html);
        $this->assertStringContainsString('bg-mint-100', $html);
        $this->assertStringContainsString('text-mint-800', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringNotContainsString('rounded-full', $html);

        $html = Blade::render('<x-app.tag icon="pencil-square">Draf</x-app.tag>');
        $this->assertStringContainsString('bg-neutral-100', $html);
        $this->assertStringContainsString('text-neutral-900', $html);
    }

    public function test_compact_date_range(): void
    {
        $d = fn (string $s) => CarbonImmutable::parse($s);
        $this->assertSame('3–21 Februari 2026', Tanggal::rentang($d('2026-02-03'), $d('2026-02-21')));
        $this->assertSame('28 Februari – 3 Maret 2026', Tanggal::rentang($d('2026-02-28'), $d('2026-03-03')));
        $this->assertSame('30 Desember 2026 – 2 Januari 2027', Tanggal::rentang($d('2026-12-30'), $d('2027-01-02')));
    }
}
