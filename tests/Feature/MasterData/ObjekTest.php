<?php

namespace Tests\Feature\MasterData;

use App\Actions\MasterData\DeleteObjek;
use App\Actions\MasterData\SaveObjek;
use App\Actions\MasterData\SaveWilayahIrban;
use App\Enums\JenisObjek;
use App\Enums\Role;
use App\Livewire\Admin\Objek as ObjekPage;
use App\Livewire\Admin\WilayahIrban as WilayahPage;
use App\Models\Irban;
use App\Models\IrbanWilayah;
use App\Models\Objek;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ObjekTest extends TestCase
{
    use RefreshDatabase;

    private const PARENT_MSG = 'Desa, sekolah, puskesmas, dan BUMDes wajib punya induk kecamatan.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tahun(): int
    {
        return (int) now('Asia/Jakarta')->year;
    }

    private function kec(string $nama = 'Muara Enim'): Objek
    {
        return Objek::create(['nama' => $nama, 'jenis' => 'kecamatan']);
    }

    private function msgs(callable $fn): array
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->all();
        }
        $this->fail('Expected ValidationException');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    public function test_child_jenis_require_kecamatan_parent(): void
    {
        $kec = $this->kec();
        foreach (['desa', 'sekolah', 'puskesmas', 'bumdes'] as $j) {
            $this->assertContains(self::PARENT_MSG, $this->msgs(fn () => app(SaveObjek::class)->handle(null, 'X', $j, null)));
            $o = app(SaveObjek::class)->handle(null, "X $j", $j, $kec->id);
            $this->assertSame($kec->id, $o->parent_id);
        }
        $dinas = Objek::create(['nama' => 'Dinas A', 'jenis' => 'dinas_badan']);
        $this->assertContains(self::PARENT_MSG, $this->msgs(fn () => app(SaveObjek::class)->handle(null, 'D', 'desa', $dinas->id)));
    }

    public function test_top_level_jenis_cannot_have_parent(): void
    {
        $kec = $this->kec();
        foreach (['kecamatan', 'rsud', 'dinas_badan', 'bumd'] as $j) {
            $this->assertNotEmpty($this->msgs(fn () => app(SaveObjek::class)->handle(null, 'X', $j, $kec->id)));
            $this->assertNull(app(SaveObjek::class)->handle(null, "Y $j", $j, null)->parent_id);
        }
    }

    public function test_changing_jenis_rechecks_rule_and_kecamatan_with_children_is_locked(): void
    {
        $kec = $this->kec();
        $desa = app(SaveObjek::class)->handle(null, 'Desa 1', 'desa', $kec->id);
        // desa -> dinas_badan keeping its parent is rejected; desa without parent is rejected too.
        $this->assertNotEmpty($this->msgs(fn () => app(SaveObjek::class)->handle($desa, 'Desa 1', 'dinas_badan', $kec->id)));
        $this->assertContains(self::PARENT_MSG, $this->msgs(fn () => app(SaveObjek::class)->handle($desa, 'Desa 1', 'desa', null)));
        $this->assertNotEmpty($this->msgs(fn () => app(SaveObjek::class)->handle($kec, 'Muara Enim', 'dinas_badan', null)));
        $this->assertSame('kecamatan', $kec->fresh()->jenis->value);
    }

    public function test_delete_blocked_with_children_or_wilayah(): void
    {
        $kec = $this->kec();
        $desa = app(SaveObjek::class)->handle(null, 'Desa 1', 'desa', $kec->id);
        $this->assertNotEmpty($this->msgs(fn () => app(DeleteObjek::class)->handle($kec)));
        app(DeleteObjek::class)->handle($desa);
        $irban = Irban::factory()->create();
        IrbanWilayah::create(['irban_id' => $irban->id, 'tahun' => $this->tahun(), 'objek_id' => $kec->id]);
        $this->assertNotEmpty($this->msgs(fn () => app(DeleteObjek::class)->handle($kec)));
        $this->assertDatabaseHas('objek', ['id' => $kec->id]);
    }

    public function test_irban_referenced_by_wilayah_cannot_be_deleted(): void
    {
        $irban = Irban::factory()->create();
        IrbanWilayah::create(['irban_id' => $irban->id, 'tahun' => $this->tahun(), 'objek_id' => $this->kec()->id]);
        $this->assertTrue($irban->isReferenced());
    }

    public function test_tree_query_lists_kecamatan_with_children(): void
    {
        $kec = $this->kec();
        app(SaveObjek::class)->handle(null, 'Desa 1', 'desa', $kec->id);
        $this->kec('Ujan Mas');
        $roots = Objek::where('jenis', 'kecamatan')->with('children')->orderBy('nama')->get();
        $this->assertSame(['Muara Enim', 'Ujan Mas'], $roots->pluck('nama')->all());
        $this->assertSame(['Desa 1'], $roots[0]->children->pluck('nama')->all());
        $this->assertSame('Desa', $roots[0]->children[0]->jenis->label());
    }

    public function test_wilayah_one_irban_per_objek_per_year_and_allowed_jenis(): void
    {
        [$a, $b] = [Irban::factory()->create(), Irban::factory()->create()];
        $kec = $this->kec();
        $desa = app(SaveObjek::class)->handle(null, 'Desa 1', 'desa', $kec->id);
        $t = $this->tahun();
        app(SaveWilayahIrban::class)->handle($a, $t, [$kec->id]);
        $this->assertNotEmpty($this->msgs(fn () => app(SaveWilayahIrban::class)->handle($b, $t, [$kec->id])));
        $this->assertNotEmpty($this->msgs(fn () => app(SaveWilayahIrban::class)->handle($b, $t, [$desa->id])));
        $this->assertNotEmpty($this->msgs(fn () => app(SaveWilayahIrban::class)->handle($b, $t + 5, [])));
        app(SaveWilayahIrban::class)->handle($b, $t - 1, [$kec->id]);
        // replacing the set removes dropped objek
        app(SaveWilayahIrban::class)->handle($a, $t, []);
        $this->assertSame(0, IrbanWilayah::where('irban_id', $a->id)->count());
        app(SaveWilayahIrban::class)->handle($b, $t, [$kec->id]);
        $this->assertSame(1, IrbanWilayah::where('irban_id', $b->id)->where('tahun', $t)->count());
    }

    public function test_seeders_give_irban_i_its_sample_wilayah(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);
        $irban = Irban::where('nama', 'Irban I')->first();
        $w = IrbanWilayah::with('objek')->where(['irban_id' => $irban->id, 'tahun' => $this->tahun()])->get();
        $count = fn (JenisObjek $j) => $w->filter(fn ($r) => $r->objek->jenis === $j)->count();
        $this->assertSame(5, $count(JenisObjek::Kecamatan));
        $this->assertSame(9, $count(JenisObjek::DinasBadan));
        $this->assertSame(1, $count(JenisObjek::Bumd));
        $this->assertGreaterThan(0, Objek::whereNotNull('parent_id')->count());
    }

    public function test_pages_forbid_non_admin_and_demoted_admin_actions(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Irban]))->get(route('admin.objek'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => Role::Irban]))->get(route('admin.wilayah-irban'))->assertForbidden();

        $admin = $this->admin();
        $kec = $this->kec();
        $calls = [[ObjekPage::class, 'create'], [ObjekPage::class, 'addChild', $kec->id], [ObjekPage::class, 'edit', $kec->id], [ObjekPage::class, 'save'],
            [ObjekPage::class, 'confirmDelete', $kec->id], [ObjekPage::class, 'delete'], [WilayahPage::class, 'save', 1]];
        foreach ($calls as $call) {
            [$class, $method, $arg] = $call + [null, null, null];
            $admin->role = Role::Admin;
            $c = Livewire::actingAs($admin)->test($class);
            if ($class === ObjekPage::class) {
                $c->call('confirmDelete', $kec->id);
            }
            $admin->role = Role::Irban;
            $c->call($method, ...($arg !== null ? [$arg] : []))->assertForbidden();
        }
        $this->assertModelExists($kec);
    }

    public function test_objek_page_creates_child_and_blocks_delete_with_toast(): void
    {
        $kec = $this->kec();
        $c = Livewire::actingAs($this->admin())->test(ObjekPage::class)
            ->call('addChild', $kec->id)
            ->assertSet('parentId', $kec->id)
            ->set('nama', 'Desa Baru')->set('jenis', 'desa')->call('save')->assertHasNoErrors();
        $this->assertDatabaseHas('objek', ['nama' => 'Desa Baru', 'parent_id' => $kec->id]);
        $c->call('confirmDelete', $kec->id)->call('delete')->assertDispatched('toast', type: 'error', message: 'Objek ini masih punya objek di bawahnya atau sudah dipakai pada wilayah Irban, sehingga tidak dapat dihapus.');
        $this->assertDatabaseHas('objek', ['id' => $kec->id]);
        $c->call('create')->set('jenis', 'desa')->set('parentId', null)->set('nama', 'Z')->call('save')->assertHasErrors('parentId');
    }

    public function test_wilayah_page_saves_and_reports_conflict(): void
    {
        [$a, $b] = [Irban::factory()->create(), Irban::factory()->create()];
        $kec = $this->kec();
        $p = Livewire::actingAs($this->admin())->test(WilayahPage::class)
            ->set("selected.{$a->id}", [$kec->id])->call('save', $a->id)->assertHasNoErrors();
        $this->assertDatabaseHas('irban_wilayah', ['irban_id' => $a->id, 'objek_id' => $kec->id]);
        $p->set("selected.{$b->id}", [$kec->id])->call('save', $b->id)->assertHasErrors("selected.{$b->id}");
    }
}
