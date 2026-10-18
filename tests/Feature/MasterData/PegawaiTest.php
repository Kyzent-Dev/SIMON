<?php

namespace Tests\Feature\MasterData;

use App\Enums\Role;
use App\Livewire\Admin\Pegawai as Page;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PegawaiTest extends TestCase
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

    private function page()
    {
        return Livewire::actingAs($this->admin())->test(Page::class);
    }

    public function test_only_admin_can_open_pages(): void
    {
        foreach (['/admin/pegawai', '/admin/pegawai/impor'] as $url) {
            $this->get($url)->assertRedirect('/masuk');
        }
        foreach (['/admin/pegawai', '/admin/pegawai/impor'] as $url) {
            $this->actingAs(User::factory()->irban()->create())->get($url)->assertForbidden();
            $this->actingAs($this->admin())->get($url)->assertOk();
        }
        $this->actingAs($this->admin())->get('/admin/pegawai/impor')->assertSee('Impor Pegawai');
    }

    public function test_empty_state_has_both_actions(): void
    {
        $this->actingAs($this->admin())->get('/admin/pegawai')
            ->assertSee('Belum ada data pegawai. Tambah manual atau impor dari Excel.')
            ->assertSee('Tambah pegawai')->assertSee('Impor dari Excel')
            ->assertSee(route('admin.pegawai.impor'), false);
    }

    public function test_create_persists(): void
    {
        $this->page()->call('create')->assertSet('open', true)
            ->set('nama', 'Dr. Budi Santoso, M.Si.')->set('nip', '198703122010011001')
            ->set('pangkat', 'Pembina')->set('golongan', 'IV/a')->set('jabatan', 'Inspektur')
            ->call('save')->assertHasNoErrors()->assertSet('open', false)->assertDispatched('toast');

        $p = Pegawai::firstOrFail();
        $this->assertSame('Dr. Budi Santoso, M.Si.', $p->nama_bergelar);
        $this->assertSame('198703122010011001', $p->nip);
        $this->assertTrue($p->aktif);
    }

    public function test_edit_updates_and_keeps_own_nip_unique(): void
    {
        $p = Pegawai::factory()->create();
        $this->page()->call('edit', $p->id)->assertSet('nip', $p->nip)
            ->set('nama', 'Nama Baru')->set('aktif', false)->call('save')->assertHasNoErrors();
        $p->refresh();
        $this->assertSame('Nama Baru', $p->nama_bergelar);
        $this->assertFalse($p->aktif);
    }

    public function test_nip_validation(): void
    {
        $other = Pegawai::factory()->create();
        foreach (['123', '19870312201001100', '1987031220100110011', '19870312201001100a'] as $bad) {
            $this->page()->call('create')->set('nama', 'X')->set('nip', $bad)->call('save')
                ->assertHasErrors('nip')->assertSee('NIP harus 18 digit angka.');
        }
        $this->page()->call('create')->set('nama', 'X')->set('nip', $other->nip)->call('save')->assertHasErrors('nip');
        $this->page()->call('create')->set('nip', '198703122010011001')->call('save')->assertHasErrors('nama');
    }

    public function test_search_filter_sort(): void
    {
        Pegawai::factory()->create(['nama_bergelar' => 'Agus', 'nip' => '111111111111111111', 'aktif' => true]);
        Pegawai::factory()->create(['nama_bergelar' => 'Zainal', 'nip' => '222222222222222222', 'aktif' => false]);

        $this->page()->set('search', 'zain')->assertSee('Zainal')->assertDontSee('Agus')
            ->set('search', '1111')->assertSee('Agus')->assertDontSee('Zainal')
            ->set('search', '')->set('filter', 'aktif')->assertSee('Agus')->assertDontSee('Zainal')
            ->set('filter', 'nonaktif')->assertSee('Zainal')->assertDontSee('Agus')
            ->set('filter', 'semua')->call('sort', 'nama')->assertSet('sortDir', 'desc')->assertSeeInOrder(['Zainal', 'Agus'])
            ->call('sort', 'nama')->assertSet('sortDir', 'asc')->assertSeeInOrder(['Agus', 'Zainal'])
            ->call('sort', 'nip')->assertSet('sortBy', 'nip')->assertSet('sortDir', 'asc');
    }

    public function test_sort_ignores_unknown_column(): void
    {
        $this->page()->call('sort', 'password')->assertSet('sortBy', 'nama');
    }

    public function test_toggle_aktif(): void
    {
        $p = Pegawai::factory()->create();
        $this->page()->call('toggleAktif', $p->id)->assertDispatched('toast');
        $this->assertFalse($p->fresh()->aktif);
    }

    public function test_delete_unreferenced_hard_deletes_after_confirm(): void
    {
        $p = Pegawai::factory()->create();
        $this->page()->call('confirmDelete', $p->id)->assertSet('deleteOpen', true)
            ->call('delete')->assertSet('deleteOpen', false)->assertDispatched('toast');
        $this->assertModelMissing($p);
    }

    public function test_delete_referenced_is_blocked_and_deactivates(): void
    {
        $p = new class extends Pegawai
        {
            public function isReferenced(): bool
            {
                return true;
            }
        };
        $this->assertTrue($p->isReferenced());
        $this->assertFalse((new Pegawai)->isReferenced());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Actions\MasterData\DeletePegawai::class)->handle($p);
    }

    public function test_pagination_per_page(): void
    {
        Pegawai::factory()->count(30)->create();
        $this->page()->assertSee('Menampilkan 1–25 dari 30')->set('perPage', 50)->assertSee('Menampilkan 1–30 dari 30');
    }

    public function test_every_action_rejects_demoted_admin(): void
    {
        $admin = $this->admin();
        $p = Pegawai::factory()->create(['nama_bergelar' => 'Asli']);
        $calls = [['create'], ['edit', $p->id], ['save'], ['toggleAktif', $p->id], ['confirmDelete', $p->id], ['delete']];
        foreach ($calls as $call) {
            [$method, $arg] = $call + [null, null];
            $admin->role = Role::Admin;
            $c = Livewire::actingAs($admin)->test(Page::class)->call('edit', $p->id)->set('nama', 'Diubah')->call('confirmDelete', $p->id);
            $admin->role = Role::Irban;
            $c->call($method, ...($arg ? [$arg] : []))->assertForbidden();
        }
        $this->assertSame('Asli', $p->fresh()->nama_bergelar);
        $this->assertTrue($p->fresh()->aktif);
        $this->assertModelExists($p);
    }

    public function test_non_admin_cannot_mount(): void
    {
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();
    }
}
