<?php

namespace Tests\Feature\MasterData;

use App\Enums\Role;
use App\Livewire\Admin\JenisPenugasan as Page;
use App\Models\JenisPenugasan;
use App\Models\User;
use App\Services\ColorRules;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JenisPenugasanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Page::class);
    }

    public function test_only_admin_can_open_page(): void
    {
        $this->get('/admin/jenis-penugasan')->assertRedirect('/masuk');
        $this->actingAs(User::factory()->irban()->create())->get('/admin/jenis-penugasan')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/jenis-penugasan')->assertOk()->assertSee('Jenis Penugasan');
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();
    }

    public function test_each_swatch_is_saved_with_its_stated_label(): void
    {
        foreach (ColorRules::SWATCHES as $s) {
            $this->page()->call('create')->set('nama', "Jenis {$s['id']}")->set('swatch', (string) $s['id'])
                ->call('save')->assertHasNoErrors();
            $row = JenisPenugasan::where('nama', "Jenis {$s['id']}")->firstOrFail();
            $this->assertSame($s['hex'], $row->warna);
            $this->assertSame($s['label'], $row->warna_teks);
        }
    }

    public function test_custom_color_is_normalized_and_label_auto_chosen(): void
    {
        $this->page()->call('create')->set('nama', 'Kustom')->set('swatch', 'custom')->set('custom', 'a0c')
            ->call('save')->assertHasNoErrors();
        $row = JenisPenugasan::firstOrFail();
        $this->assertSame('#AA00CC', $row->warna);
        $this->assertSame(ColorRules::labelFor('#AA00CC'), $row->warna_teks);
    }

    public function test_bad_custom_colors_are_rejected(): void
    {
        foreach (['#2A8A70', '#1D6B34', '#FFFF99', '#808080', 'zzz'] as $hex) {
            $this->page()->call('create')->set('nama', 'X')->set('swatch', 'custom')->set('custom', $hex)
                ->call('save')->assertHasErrors('custom');
        }
        $this->assertSame(0, JenisPenugasan::count());
    }

    public function test_reuse_is_a_warning_and_still_saves(): void
    {
        JenisPenugasan::factory()->create(['warna' => '#332288', 'warna_teks' => '#FFFFFF']);
        $this->page()->call('create')->set('nama', 'Baru')->set('swatch', '1')
            ->assertSee('Warna ini sudah dipakai oleh jenis penugasan lain.')
            ->call('save')->assertHasNoErrors();
        $this->assertSame(2, JenisPenugasan::count());
    }

    public function test_edit_does_not_warn_about_itself(): void
    {
        $j = JenisPenugasan::factory()->create(['warna' => '#332288', 'warna_teks' => '#FFFFFF']);
        $this->page()->call('edit', $j->id)->assertSet('swatch', '1')->assertDontSee('sudah dipakai oleh jenis penugasan lain');
    }

    public function test_edit_of_custom_color_prefills_custom(): void
    {
        $j = JenisPenugasan::factory()->create(['warna' => '#AA00CC', 'warna_teks' => '#FFFFFF']);
        $this->page()->call('edit', $j->id)->assertSet('swatch', 'custom')->assertSet('custom', '#AA00CC');
    }

    public function test_duplicate_name_is_a_validation_error(): void
    {
        JenisPenugasan::factory()->create(['nama' => 'Reviu']);
        $this->page()->call('create')->set('nama', 'Reviu')->set('swatch', '2')->call('save')->assertHasErrors('nama');
        $this->assertSame(1, JenisPenugasan::count());
    }

    public function test_delete_with_confirm(): void
    {
        $j = JenisPenugasan::factory()->create();
        $this->page()->call('confirmDelete', $j->id)->assertSet('deleteOpen', true)->call('delete')->assertDispatched('toast');
        $this->assertSame(0, JenisPenugasan::count());
    }

    public function test_every_action_rejects_demoted_admin(): void
    {
        $j = JenisPenugasan::factory()->create(['nama' => 'Asli']);
        $admin = User::factory()->admin()->create();
        foreach ([['create'], ['edit', $j->id], ['save'], ['confirmDelete', $j->id], ['delete']] as $call) {
            [$method, $arg] = $call + [null, null];
            $admin->role = Role::Admin;
            $c = Livewire::actingAs($admin)->test(Page::class)->call('edit', $j->id)->set('nama', 'Diubah')->call('confirmDelete', $j->id);
            $admin->role = Role::Irban;
            $c->call($method, ...($arg !== null ? [$arg] : []))->assertForbidden();
        }
        $this->assertSame('Asli', $j->fresh()->nama);
    }

    public function test_seeder_adds_three_sample_types(): void
    {
        $this->seed(DatabaseSeeder::class);
        $rows = JenisPenugasan::orderBy('id')->limit(3)->get(); // later ids come from PkptIrbanIDemo2026Seeder
        $this->assertSame(['#332288', '#C77C00', '#3F6FA6'], $rows->pluck('warna')->all());
        $this->assertSame('Reviu LKjIP', $rows[2]->nama);
    }
}
