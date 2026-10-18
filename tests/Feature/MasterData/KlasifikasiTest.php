<?php

namespace Tests\Feature\MasterData;

use App\Enums\SyaratSelesai;
use App\Livewire\Admin\Klasifikasi as Page;
use App\Models\Klasifikasi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KlasifikasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_seeded_exactly_per_prd_9_4(): void
    {
        $rows = Klasifikasi::orderBy('id')->get()
            ->map(fn ($k) => [$k->kode, $k->nama, $k->wajib_item_pkpt, $k->syarat_selesai->value, $k->tampil_publik])->all();
        $this->assertSame([
            ['REGULER', 'REGULER', true, 'lhp_lengkap', true],
            ['KHUSUS', 'KHUSUS', false, 'lhp_lengkap', false],
            ['DIKLAT', 'DIKLAT', false, 'konfirmasi', false],
            ['LUAR_DAERAH', 'LUAR DAERAH', false, 'laporan_opsional', false],
            ['LAIN_LAIN', 'LAIN-LAIN', false, 'dokumen_opsional', false],
        ], $rows);
    }

    public function test_enum_labels(): void
    {
        $this->assertSame('Cover LHP + nomor + tanggal LHP', SyaratSelesai::LhpLengkap->label());
        $this->assertSame('Dokumen opsional', SyaratSelesai::DokumenOpsional->label());
    }

    public function test_only_admin_can_open(): void
    {
        $this->get('/admin/klasifikasi')->assertRedirect('/masuk');
        $this->actingAs(User::factory()->irban()->create())->get('/admin/klasifikasi')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/klasifikasi')->assertOk()
            ->assertSee('Klasifikasi &amp; Aturan', false)->assertSee('LUAR DAERAH')->assertSee('Laporan perjalanan opsional');
    }

    public function test_update_persists(): void
    {
        $k = Klasifikasi::where('kode', 'KHUSUS')->first();
        Livewire::actingAs(User::factory()->admin()->create())->test(Page::class)
            ->call('edit', $k->id)
            ->assertSet('open', true)
            ->set('wajibItemPkpt', true)->set('syaratSelesai', 'konfirmasi')->set('tampilPublik', true)
            ->call('save')->assertHasNoErrors()->assertSet('open', false)->assertDispatched('toast');

        $k->refresh();
        $this->assertTrue($k->wajib_item_pkpt);
        $this->assertTrue($k->tampil_publik);
        $this->assertSame(SyaratSelesai::Konfirmasi, $k->syarat_selesai);
        $this->assertSame('KHUSUS', $k->kode);
    }

    public function test_invalid_syarat_rejected(): void
    {
        $k = Klasifikasi::first();
        Livewire::actingAs(User::factory()->admin()->create())->test(Page::class)
            ->call('edit', $k->id)->set('syaratSelesai', 'bebas')->call('save')->assertHasErrors('syaratSelesai');
    }

    public function test_non_admin_cannot_edit(): void
    {
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();
    }

    public function test_no_create_or_delete(): void
    {
        $this->assertFalse(method_exists(Page::class, 'create'));
        $this->assertFalse(method_exists(Page::class, 'delete'));
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post('/admin/klasifikasi')->assertStatus(405);
        $this->actingAs($admin)->delete('/admin/klasifikasi/1')->assertStatus(404);
        $this->assertCount(5, Klasifikasi::all());
    }
}
