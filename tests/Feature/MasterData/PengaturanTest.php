<?php

namespace Tests\Feature\MasterData;

use App\Enums\Role;
use App\Livewire\Admin\Pengaturan;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PengaturanTest extends TestCase
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

    public function test_only_admin_can_open(): void
    {
        $this->get('/admin/pengaturan')->assertRedirect('/masuk');
        foreach (['pimpinan', 'irban', 'bagianUmum'] as $role) {
            $this->actingAs(User::factory()->$role()->create())->get('/admin/pengaturan')->assertForbidden();
        }
        $this->actingAs($this->admin())->get('/admin/pengaturan')->assertOk()
            ->assertSee('Pengaturan Sistem')->assertSee('Simpan perubahan')->assertSee('Kategori kendala');
    }

    public function test_non_admin_cannot_call_save(): void
    {
        Livewire::actingAs(User::factory()->irban()->create())->test(Pengaturan::class)->assertForbidden();
    }

    public function test_each_card_saves_only_its_own_keys(): void
    {
        $s = app(Settings::class);
        Livewire::actingAs($this->admin())->test(Pengaturan::class)
            ->set('v.penomoran.kode_arsip', '800')
            ->set('v.upload.maks_mb', 50)
            ->call('save', 'penomoran')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertSame('800', $s->get('penomoran.kode_arsip'));
        $this->assertSame(10, $s->get('upload.maks_mb'));
        $this->assertDatabaseMissing('settings', ['key' => 'upload.maks_mb']);
    }

    public function test_format_requires_nomor_urut_and_tahun(): void
    {
        Livewire::actingAs($this->admin())->test(Pengaturan::class)
            ->set('v.penomoran.format', '{kode_arsip}/{tahun}')
            ->call('save', 'penomoran')->assertHasErrors('v.penomoran.format');
        Livewire::actingAs($this->admin())->test(Pengaturan::class)
            ->set('v.penomoran.format', '{nomor_urut}/{tahun}')
            ->call('save', 'penomoran')->assertHasNoErrors();
    }

    public function test_enum_and_number_validation(): void
    {
        Livewire::actingAs($this->admin())->test(Pengaturan::class)
            ->set('v.penomoran.scope', 'lain')->call('save', 'penomoran')->assertHasErrors('v.penomoran.scope')
            ->set('v.penanda.batas_terlambat_hari', 0)->call('save', 'penugasan')->assertHasErrors('v.penanda.batas_terlambat_hari')
            ->set('v.upload.maks_mb', 'abc')->call('save', 'unggahan')->assertHasErrors('v.upload.maks_mb');
    }

    public function test_kategori_add_remove_min_one_and_lainnya_stays(): void
    {
        $c = Livewire::actingAs($this->admin())->test(Pengaturan::class)
            ->call('addKategori')->assertSet('v.kendala.kategori.5', '')
            ->call('save', 'kendala')->assertHasErrors('v.kendala.kategori.5')
            ->set('v.kendala.kategori.5', 'Cuaca buruk')
            ->call('save', 'kendala')->assertHasNoErrors();
        $this->assertContains('Cuaca buruk', app(Settings::class)->get('kendala.kategori'));

        $c->call('removeKategori', 5)->assertCount('v.kendala.kategori', 5);

        // "Lainnya" cannot be removed.
        $c->call('removeKategori', 4)->assertCount('v.kendala.kategori', 5);

        // Minimum one.
        $c->set('v.kendala.kategori', [])->call('save', 'kendala')->assertHasErrors('v.kendala.kategori');
        $c->set('v.kendala.kategori', ['Satu'])->call('save', 'kendala')->assertHasErrors('v.kendala.kategori'); // missing Lainnya
    }

    public function test_saved_values_are_loaded(): void
    {
        app(Settings::class)->set('penomoran.kode_arsip', '123');
        Livewire::actingAs($this->admin())->test(Pengaturan::class)->assertSet('v.penomoran.kode_arsip', '123');
    }

    public function test_demoted_admin_is_rejected_on_every_action_and_nothing_changes(): void
    {
        $admin = $this->admin();
        // A forbidden call leaves the test component unusable, so mount a fresh one per action, then demote.
        $demoted = function (string $method, ...$args) use ($admin) {
            $admin->role = Role::Admin;
            $c = Livewire::actingAs($admin)->test(Pengaturan::class)->set('v.penomoran.kode_arsip', '999')->set('v.upload.maks_mb', 77);
            $admin->role = Role::Irban;
            $c->call($method, ...$args)->assertForbidden();
        };

        foreach (array_keys(Settings::GROUPS) as $group) {
            $demoted('save', $group);
        }
        $demoted('addKategori');
        $demoted('removeKategori', 0);

        $this->assertDatabaseCount('settings', 0);
        $this->assertCount(5, app(Settings::class)->get('kendala.kategori'));
    }

    public function test_unknown_group_is_404(): void
    {
        Livewire::actingAs($this->admin())->test(Pengaturan::class)->call('save', 'nope')->assertNotFound();
    }

    public function test_kategori_values_are_trimmed_before_validation_and_saved(): void
    {
        Livewire::actingAs($this->admin())->test(Pengaturan::class)
            ->set('v.kendala.kategori.4', 'Lainnya ')
            ->call('addKategori')->set('v.kendala.kategori.5', '  Cuaca buruk  ')
            ->call('save', 'kendala')->assertHasNoErrors();
        $saved = app(Settings::class)->get('kendala.kategori');
        $this->assertSame('Lainnya', $saved[4]);
        $this->assertSame('Cuaca buruk', $saved[5]);
    }

    public function test_removing_a_middle_kategori_keeps_the_right_rows_and_stable_ids(): void
    {
        $c = Livewire::actingAs($this->admin())->test(Pengaturan::class);
        $ids = $c->get('kategoriIds');
        $c->call('removeKategori', 1);

        $this->assertSame(
            ['Jadwal PKPT belum disahkan', 'Personel tidak tersedia', 'Objek tidak siap', 'Lainnya'],
            $c->get('v.kendala.kategori'),
        );
        $this->assertSame([$ids[0], $ids[2], $ids[3], $ids[4]], $c->get('kategoriIds'));
        $c->assertSeeHtml('wire:key="kategori-'.$ids[2].'"')->assertDontSeeHtml('wire:key="kategori-'.$ids[1].'"');
    }
}
