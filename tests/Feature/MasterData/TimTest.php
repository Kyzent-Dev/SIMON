<?php

namespace Tests\Feature\MasterData;

use App\Actions\MasterData\SaveTim;
use App\Actions\MasterData\ValidateTimComposition;
use App\Enums\PeranTim;
use App\Enums\Role;
use App\Livewire\Admin\Tim as Page;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Tim;
use App\Models\User;
use Database\Seeders\IrbanSeeder;
use Database\Seeders\TimSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TimTest extends TestCase
{
    use RefreshDatabase;

    private const SUP = 'Setiap tim wajib punya tepat satu Supervisor/Dalnis.';

    private const KETUA = 'Setiap tim wajib punya tepat satu Ketua.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function tahun(): int
    {
        return (int) now('Asia/Jakarta')->year;
    }

    /** @param list<string> $perans */
    private function members(array $perans): array
    {
        return array_map(fn ($p) => ['pegawai_id' => Pegawai::factory()->create()->id, 'peran_default' => $p], $perans);
    }

    private function save(array $members, string $nama = 'Tim 1'): Tim
    {
        return app(SaveTim::class)->handle(Irban::factory()->create(), $this->tahun(), null, $nama, $members);
    }

    private function assertRejected(array $members, string $message): void
    {
        try {
            $this->save($members);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertContains($message, collect($e->errors())->flatten()->all());
        }
        $this->assertSame(0, Tim::count());
    }

    public function test_zero_supervisor_is_rejected_with_exact_message(): void
    {
        $this->assertRejected($this->members(['ketua', 'anggota']), self::SUP);
    }

    public function test_two_supervisor_is_rejected(): void
    {
        $this->assertRejected($this->members(['supervisor_dalnis', 'supervisor_dalnis', 'ketua']), self::SUP);
    }

    public function test_zero_ketua_is_rejected_with_exact_message(): void
    {
        $this->assertRejected($this->members(['supervisor_dalnis', 'anggota']), self::KETUA);
    }

    public function test_two_ketua_is_rejected(): void
    {
        $this->assertRejected($this->members(['supervisor_dalnis', 'ketua', 'ketua']), self::KETUA);
    }

    public function test_exactly_one_supervisor_and_one_ketua_with_anggota_is_saved(): void
    {
        $tim = $this->save($this->members(['anggota', 'ketua', 'supervisor_dalnis', 'anggota']));
        $rows = $tim->anggota()->get();
        $this->assertSame([1, 2, 3, 4], $rows->pluck('urutan')->all());
        $this->assertSame(PeranTim::Anggota, $rows[0]->peran_default);
        $this->assertSame(PeranTim::SupervisorDalnis, $rows[2]->peran_default);
    }

    public function test_duplicate_pegawai_is_rejected(): void
    {
        $m = $this->members(['supervisor_dalnis', 'ketua']);
        $m[] = ['pegawai_id' => $m[0]['pegawai_id'], 'peran_default' => 'anggota'];
        $this->assertRejected($m, 'Pegawai tidak boleh dipilih dua kali dalam satu tim.');
    }

    public function test_validator_enforces_rules_when_called_directly(): void
    {
        $v = new ValidateTimComposition;
        $v->handle($this->members(['supervisor_dalnis', 'ketua']));
        $this->expectException(ValidationException::class);
        $v->handle([]);
    }

    public function test_unknown_peran_and_inactive_pegawai_are_rejected(): void
    {
        $m = $this->members(['supervisor_dalnis', 'ketua']);
        $m[] = ['pegawai_id' => Pegawai::factory()->create(['aktif' => false])->id, 'peran_default' => 'anggota'];
        $this->assertRejected($m, 'Pilih pegawai aktif.');
        $m = $this->members(['supervisor_dalnis', 'ketua', 'bos']);
        $this->assertRejected($m, 'Pilih peran yang valid.');
    }

    public function test_update_replaces_members_and_keeps_inactive_existing_member(): void
    {
        $tim = $this->save($m = $this->members(['supervisor_dalnis', 'ketua', 'anggota']));
        Pegawai::find($m[2]['pegawai_id'])->update(['aktif' => false]);
        app(SaveTim::class)->handle($tim->irban, $tim->tahun, $tim, 'Tim 1', array_reverse($m));
        $this->assertSame(array_reverse(array_column($m, 'pegawai_id')), $tim->anggota()->pluck('pegawai_id')->all());
        $this->assertSame(3, $tim->anggota()->count());
    }

    public function test_failed_update_leaves_existing_members_untouched(): void
    {
        $tim = $this->save($m = $this->members(['supervisor_dalnis', 'ketua']));
        try {
            app(SaveTim::class)->handle($tim->irban, $tim->tahun, $tim, 'Tim 1', [$m[0]]);
        } catch (ValidationException) {
        }
        $this->assertSame(2, $tim->anggota()->count());
    }

    public function test_name_must_be_unique_per_irban_and_year(): void
    {
        $tim = $this->save($this->members(['supervisor_dalnis', 'ketua']));
        $this->expectException(ValidationException::class);
        app(SaveTim::class)->handle($tim->irban, $tim->tahun, null, 'Tim 1', $this->members(['supervisor_dalnis', 'ketua']));
    }

    public function test_year_out_of_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        app(SaveTim::class)->handle(Irban::factory()->create(), 1999, null, 'Tim 1', $this->members(['supervisor_dalnis', 'ketua']));
    }

    public function test_pegawai_and_irban_with_tim_are_referenced(): void
    {
        $tim = $this->save($m = $this->members(['supervisor_dalnis', 'ketua']));
        $this->assertTrue(Pegawai::find($m[0]['pegawai_id'])->isReferenced());
        $this->assertTrue($tim->irban->isReferenced());
        $this->assertFalse(Pegawai::factory()->create()->isReferenced());
        $this->assertFalse(Irban::factory()->create()->isReferenced());
    }

    public function test_deleting_tim_cascades_members(): void
    {
        $tim = $this->save($this->members(['supervisor_dalnis', 'ketua']));
        $tim->delete();
        $this->assertDatabaseCount('tim_anggota', 0);
    }

    // ---- Livewire page ----

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Page::class);
    }

    public function test_access(): void
    {
        $this->get('/admin/tim')->assertRedirect('/masuk');
        $this->actingAs(User::factory()->irban()->create())->get('/admin/tim')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/tim')->assertOk()->assertSee('Tim');
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();
    }

    public function test_page_creates_tim_with_default_name_and_blocks_invalid_save(): void
    {
        $irban = Irban::factory()->create();
        [$a, $b, $c] = Pegawai::factory()->count(3)->create();
        $p = $this->page()->set('irbanId', $irban->id)->call('create')->assertSet('nama', 'Tim 1');
        $p->set('newPegawai', $a->id)->set('newPeran', 'ketua')->call('addMember')
            ->assertSet('newPegawai', null);
        $p->call('save')->assertHasErrors(['members'])->assertSee(self::SUP);
        $this->assertSame(0, Tim::count());
        $p->set('newPegawai', $b->id)->set('newPeran', 'supervisor_dalnis')->call('addMember')
            ->set('newPegawai', $c->id)->set('newPeran', 'anggota')->call('addMember')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast', type: 'success');
        $this->assertSame(['ketua', 'supervisor_dalnis', 'anggota'], Tim::firstOrFail()->anggota->map(fn ($m) => $m->peran_default->value)->all());
        $this->page()->set('irbanId', $irban->id)->call('create')->assertSet('nama', 'Tim 2');
    }

    public function test_add_member_ignores_duplicate_and_move_and_remove_work(): void
    {
        [$a, $b] = Pegawai::factory()->count(2)->create();
        $p = $this->page()->call('create')
            ->set('newPegawai', $a->id)->call('addMember')->set('newPegawai', $a->id)->call('addMember')
            ->set('newPegawai', $b->id)->call('addMember');
        $this->assertCount(2, $p->get('members'));
        $p->call('moveUp', 1);
        $this->assertSame([$b->id, $a->id], array_column($p->get('members'), 'pegawai_id'));
        $p->call('moveDown', 0);
        $this->assertSame([$a->id, $b->id], array_column($p->get('members'), 'pegawai_id'));
        $p->call('moveUp', 0)->call('moveDown', 1);
        $this->assertSame([$a->id, $b->id], array_column($p->get('members'), 'pegawai_id'));
        $p->call('removeMember', 0);
        $this->assertSame([$b->id], array_column($p->get('members'), 'pegawai_id'));
    }

    public function test_validation_strip_reports_counts_and_has_live_region(): void
    {
        $a = Pegawai::factory()->create();
        Irban::factory()->create();
        $this->page()->call('create')->set('newPegawai', $a->id)->set('newPeran', 'ketua')->call('addMember')
            ->assertSee('aria-live="polite"', false)->assertSee('Tepat satu Supervisor/Dalnis')->assertSee('Tepat satu Ketua: 1 dipilih, terpenuhi')
            ->assertSee('Tepat satu Supervisor/Dalnis: 0 dipilih, belum terpenuhi');
    }

    public function test_edit_and_delete_tim(): void
    {
        $tim = $this->save($this->members(['supervisor_dalnis', 'ketua']));
        $p = Livewire::actingAs(User::factory()->admin()->create())->test(Page::class)->set('irbanId', $tim->irban_id)
            ->call('edit', $tim->id)->assertSet('nama', 'Tim 1')->set('nama', 'Tim Khusus')->call('save')->assertHasNoErrors();
        $this->assertSame('Tim Khusus', $tim->fresh()->nama);
        $p->call('confirmDelete', $tim->id)->call('delete')->assertDispatched('toast', type: 'success');
        $this->assertSame(0, Tim::count());
        $this->assertDatabaseCount('tim_anggota', 0);
    }

    public function test_every_action_rejects_demoted_admin(): void
    {
        $tim = $this->save($this->members(['supervisor_dalnis', 'ketua']));
        $admin = User::factory()->admin()->create();
        $calls = [['create'], ['edit', $tim->id], ['addMember'], ['removeMember', 0], ['moveUp', 1], ['moveDown', 0], ['save'], ['confirmDelete', $tim->id], ['delete']];
        foreach ($calls as $call) {
            [$method, $arg] = $call + [null, null];
            $admin->role = Role::Admin;
            $c = Livewire::actingAs($admin)->test(Page::class)->set('irbanId', $tim->irban_id)->call('edit', $tim->id)->call('confirmDelete', $tim->id);
            $admin->role = Role::Irban;
            $c->call($method, ...($arg !== null ? [$arg] : []))->assertForbidden();
        }
        $this->assertModelExists($tim);
        $this->assertSame(2, $tim->anggota()->count());
    }

    public function test_seeder_creates_tim_one_for_irban_i(): void
    {
        $this->seed(IrbanSeeder::class);
        $this->seed(TimSeeder::class);
        $this->seed(TimSeeder::class);
        $tim = Tim::firstOrFail();
        $this->assertSame('Tim 1', $tim->nama);
        $this->assertSame(['supervisor_dalnis', 'ketua', 'anggota', 'anggota'], $tim->anggota->map(fn ($m) => $m->peran_default->value)->all());
        $this->assertSame(1, Tim::count());
    }
}
