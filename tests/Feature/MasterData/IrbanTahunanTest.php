<?php

namespace Tests\Feature\MasterData;

use App\Actions\MasterData\SaveIrbanTahunan;
use App\Enums\Role;
use App\Livewire\Admin\IrbanTahunan as Page;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IrbanTahunanTest extends TestCase
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

    private function tahun(): int
    {
        return (int) now('Asia/Jakarta')->year;
    }

    public function test_access_and_default_year(): void
    {
        $this->get('/admin/irban-tahunan')->assertRedirect('/masuk');
        $this->actingAs(User::factory()->irban()->create())->get('/admin/irban-tahunan')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/irban-tahunan')->assertOk()->assertSee('Komposisi Irban Tahunan');
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();
        $this->page()->assertSet('tahun', $this->tahun());
    }

    public function test_year_out_of_range_falls_back(): void
    {
        $this->page()->set('tahun', 1999)->assertSet('tahun', $this->tahun());
    }

    public function test_save_creates_row_on_demand_with_ppj_and_anggota(): void
    {
        $irban = Irban::factory()->create();
        [$ppj, $a, $b] = Pegawai::factory()->count(3)->create();
        $this->page()->set("ppj.{$irban->id}", $ppj->id)->set("anggota.{$irban->id}", [$a->id, $b->id])
            ->call('save', $irban->id)->assertHasNoErrors()->assertDispatched('toast', type: 'success');
        $it = IrbanTahunan::firstOrFail();
        $this->assertSame($this->tahun(), $it->tahun);
        $this->assertSame($ppj->id, $it->ppj_pegawai_id);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $it->anggota()->pluck('pegawai.id')->all());
    }

    public function test_ppj_is_optional_and_resave_syncs(): void
    {
        $irban = Irban::factory()->create();
        [$a, $b] = Pegawai::factory()->count(2)->create();
        $p = $this->page();
        $p->set("anggota.{$irban->id}", [$a->id])->call('save', $irban->id)->assertHasNoErrors();
        $this->assertNull(IrbanTahunan::first()->ppj_pegawai_id);
        $p->set("anggota.{$irban->id}", [$b->id])->call('save', $irban->id);
        $this->assertSame([$b->id], IrbanTahunan::first()->anggota()->pluck('pegawai.id')->all());
        $this->assertSame(1, IrbanTahunan::count());
    }

    public function test_page_loads_saved_state(): void
    {
        $it = IrbanTahunan::factory()->create(['tahun' => $this->tahun()]);
        $a = Pegawai::factory()->create();
        $it->anggota()->attach($a->id);
        $this->page()->assertSet("anggota.{$it->irban_id}", [$a->id]);
    }

    public function test_pegawai_in_two_irban_same_year_is_rejected_with_exact_message(): void
    {
        $one = Irban::factory()->create(['nama' => 'Irban I']);
        $two = Irban::factory()->create(['nama' => 'Irban II']);
        $a = Pegawai::factory()->create();
        $this->page()->set("anggota.{$one->id}", [$a->id])->call('save', $one->id)->assertHasNoErrors()
            ->set("anggota.{$two->id}", [$a->id])->call('save', $two->id)
            ->assertHasErrors("anggota.{$two->id}")
            ->assertSee('Pegawai ini sudah menjadi anggota Irban I pada tahun '.$this->tahun().'.');
        $this->assertSame(0, IrbanTahunan::where('irban_id', $two->id)->count());
    }

    public function test_action_allows_other_year_and_same_irban_resave(): void
    {
        $one = Irban::factory()->create();
        $two = Irban::factory()->create();
        $a = Pegawai::factory()->create();
        $action = app(SaveIrbanTahunan::class);
        $action->handle($one, 2026, null, [$a->id]);
        $action->handle($one, 2026, null, [$a->id]);
        $action->handle($two, 2027, null, [$a->id]);
        $this->assertSame(2, IrbanTahunan::count());
        $this->expectException(ValidationException::class);
        $action->handle($two, 2026, null, [$a->id]);
    }

    public function test_inactive_or_unknown_pegawai_rejected(): void
    {
        $irban = Irban::factory()->create();
        $inactive = Pegawai::factory()->create(['aktif' => false]);
        $this->page()->set("anggota.{$irban->id}", [$inactive->id])->call('save', $irban->id)->assertHasErrors("anggota.{$irban->id}");
        $this->page()->set("ppj.{$irban->id}", 9999)->call('save', $irban->id)->assertHasErrors("ppj.{$irban->id}");
    }

    public function test_save_rejects_demoted_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $irban = Irban::factory()->create();
        $c = Livewire::actingAs($admin)->test(Page::class);
        $admin->role = Role::Irban;
        $c->call('save', $irban->id)->assertForbidden();
        $this->assertSame(0, IrbanTahunan::count());
    }

    public function test_combobox_renders_accessibility_attributes(): void
    {
        $html = Blade::render('<x-ui.combobox name="x" label="Anggota" :options="[1 => \'Budi\']" multiple />');
        foreach (['role="combobox"', 'aria-expanded', 'aria-activedescendant', 'aria-controls', 'role="listbox"', 'aria-multiselectable="true"', 'Tidak ditemukan'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
        $single = Blade::render('<x-ui.combobox name="y" label="PPJ" :options="[1 => \'Budi\']" />');
        $this->assertStringNotContainsString('aria-multiselectable', $single);
    }
}
