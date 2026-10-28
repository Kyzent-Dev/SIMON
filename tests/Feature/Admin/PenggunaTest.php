<?php

namespace Tests\Feature\Admin;

use App\Actions\Pengguna\AturUlangKataSandi;
use App\Actions\Pengguna\SimpanPengguna;
use App\Enums\Role;
use App\Livewire\Admin\Pengguna;
use App\Models\Irban;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PenggunaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->admin()->create();
    }

    private function isi($c, array $o = [])
    {
        return $c->set('name', $o['name'] ?? 'Budi')->set('username', $o['username'] ?? 'budi.s')
            ->set('email', $o['email'] ?? 'budi@example.test')->set('role', $o['role'] ?? 'pimpinan')
            ->set('irban_id', $o['irban_id'] ?? '')->set('password', $o['password'] ?? 'Sementara1');
    }

    public function test_list_and_role_filter(): void
    {
        User::factory()->pimpinan()->create(['name' => 'Pak Pimpinan']);
        User::factory()->bagianUmum()->create(['name' => 'Bu Umum']);
        $this->actingAs($this->admin)->get('/admin/pengguna')->assertOk()->assertSee('Pak Pimpinan')->assertSee('Bu Umum');
        Livewire::actingAs($this->admin)->test(Pengguna::class)->set('peran', 'pimpinan')
            ->assertSee('Pak Pimpinan')->assertDontSee('Bu Umum');
    }

    public function test_create_each_role_with_forced_change(): void
    {
        $irban = Irban::factory()->create();
        foreach (['admin', 'pimpinan', 'bagian_umum', 'irban'] as $i => $role) {
            $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
            $this->isi($c, ['role' => $role, 'username' => "user.$i", 'email' => "u$i@example.test", 'irban_id' => $role === 'irban' ? (string) $irban->id : ''])
                ->call('save')->assertHasNoErrors();
            $u = User::where('username', "user.$i")->first();
            $this->assertSame($role, $u->role->value);
            $this->assertSame($role === 'irban' ? $irban->id : null, $u->irban_id);
            $this->assertTrue($u->wajib_ganti_password);
            $this->assertTrue(Hash::check('Sementara1', $u->password));
        }
    }

    public function test_password_is_cleared_from_state_after_create(): void
    {
        $c = Livewire::actingAs(User::factory()->admin()->create())->test(Pengguna::class);
        $this->isi($c, ['role' => 'pimpinan'])->call('save')->assertHasNoErrors()->assertSet('password', '');
    }

    public function test_irban_requires_irban_and_weak_password_rejected(): void
    {
        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
        $this->isi($c, ['role' => 'irban'])->call('save')->assertHasErrors('irban_id');
        $this->isi($c, ['role' => 'pimpinan', 'password' => 'lemah'])->call('save')->assertHasErrors('password');
    }

    public function test_username_and_email_rules(): void
    {
        User::factory()->create(['username' => 'dipakai', 'email' => 'dipakai@example.test']);
        foreach (['Budi', 'ab', str_repeat('a', 31), 'bu-di', 'dipakai'] as $bad) {
            $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
            $this->isi($c, ['username' => $bad])->call('save')->assertHasErrors('username');
        }
        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
        $this->isi($c, ['email' => 'dipakai@example.test'])->call('save')->assertHasErrors('email');
        $this->isi($c, ['username' => 'abc', 'email' => 'x@example.test'])->call('save')->assertHasNoErrors();
        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
        $this->isi($c, ['username' => str_repeat('a', 30), 'email' => 'y@example.test'])->call('save')->assertHasNoErrors();
    }

    public function test_edit_keeps_own_username_and_password(): void
    {
        $u = User::factory()->pimpinan()->create();
        $hash = $u->password;
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $u->id)->set('name', 'Baru')->call('save')->assertHasNoErrors();
        $this->assertSame('Baru', $u->fresh()->name);
        $this->assertSame($hash, $u->fresh()->password);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        User::factory()->admin()->create();
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $this->admin->id)
            ->set('role', 'pimpinan')->call('save')->assertHasErrors('role');
        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_cannot_remove_last_admin(): void
    {
        // An authorized actor is itself an admin, so the rule is reached here only with the gate forced open for a non-admin actor
        // (the self-rule does not apply); it stays as a guard behind the re-authorization.
        $this->admin->delete();
        $solo = User::factory()->admin()->create();
        $aktor = User::factory()->pimpinan()->create();
        Gate::before(fn () => true);
        $this->expectException(ValidationException::class);
        app(SimpanPengguna::class)->handle($aktor, $solo, [
            'name' => 'x', 'username' => 'abc', 'email' => 'a@b.test', 'role' => 'pimpinan', 'irban_id' => null,
        ]);
    }

    /** Final review 11.2: the actor is re-authorized from its locked row inside the transaction, so an admin demoted meanwhile is refused. */
    public function test_stale_actor_is_reauthorized_inside_the_transaction(): void
    {
        $u = User::factory()->pimpinan()->create(['name' => 'Asli']);
        User::factory()->admin()->create(); // another admin, so the demotion below is legal
        $data = ['name' => 'Diubah', 'username' => $u->username, 'email' => $u->email, 'role' => 'pimpinan', 'irban_id' => null];

        User::whereKey($this->admin->id)->update(['role' => Role::Pimpinan->value]); // demoted in another request; $this->admin is stale
        $this->assertThrows(fn () => app(SimpanPengguna::class)->handle($this->admin, $u, $data), AuthorizationException::class);
        $this->assertSame('Asli', $u->fresh()->name);
        $this->assertThrows(fn () => app(SimpanPengguna::class)->handle(User::factory()->admin()->make(['id' => 99999]), $u, $data), AuthorizationException::class); // deleted actor

        User::whereKey($this->admin->id)->update(['role' => Role::Admin->value]); // control: a current admin saves
        app(SimpanPengguna::class)->handle($this->admin, $u, $data);
        $this->assertSame('Diubah', $u->fresh()->name);
    }

    public function test_reset_password_forces_change_on_login(): void
    {
        $u = User::factory()->pimpinan()->create();
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('confirmReset', $u->id)
            ->set('sementara', 'lemah')->call('aturUlang')->assertHasErrors('sementara')
            ->set('sementara', 'Sementara1')->call('aturUlang')->assertHasNoErrors();
        $this->assertTrue($u->fresh()->wajib_ganti_password);

        auth()->logout();
        $this->post('/masuk', ['username' => $u->username, 'password' => 'Sementara1'])->assertRedirect('/pimpinan');
        $this->get('/pimpinan')->assertRedirect('/perbarui-kata-sandi');
        $this->put('/perbarui-kata-sandi', ['password' => 'Baru12345', 'password_confirmation' => 'Baru12345'])->assertRedirect();
        $this->get('/pimpinan')->assertOk();
    }

    public function test_email_is_lowercased_before_the_uniqueness_check(): void
    {
        User::factory()->create(['email' => 'dipakai@example.test']);
        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
        $this->isi($c, ['email' => 'DIPAKAI@Example.TEST'])->call('save')->assertHasErrors('email');

        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('create');
        $this->isi($c, ['email' => ' Baru@Example.TEST '])->call('save')->assertHasNoErrors();
        $this->assertSame('baru@example.test', User::where('username', 'budi.s')->value('email'));
    }

    public function test_legacy_username_is_only_validated_when_it_changes(): void
    {
        $u = User::factory()->pimpinan()->create(['username' => 'Legacy-Name']);
        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $u->id)->set('name', 'Nama Baru');
        $c->call('save')->assertHasNoErrors();
        $this->assertSame(['Nama Baru', 'Legacy-Name'], [$u->fresh()->name, $u->fresh()->username]);

        $c = Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $u->id)->set('username', 'Masih-Salah');
        $c->call('save')->assertHasErrors('username');
        $c->set('username', 'sudah.benar')->call('save')->assertHasNoErrors();
        $this->assertSame('sudah.benar', $u->fresh()->username);
    }

    public function test_tampered_role_is_rejected_and_another_admin_can_be_demoted(): void
    {
        $u = User::factory()->pimpinan()->create();
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $u->id)->set('role', 'superuser')->call('save')->assertHasErrors('role');
        $this->assertSame(Role::Pimpinan, $u->fresh()->role);

        // Positive control for the guards: demoting a different admin is allowed while the actor remains an admin.
        $lain = User::factory()->admin()->create();
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $lain->id)->set('role', 'pimpinan')->call('save')->assertHasNoErrors();
        $this->assertSame(Role::Pimpinan, $lain->fresh()->role);
        // The last admin (the actor) cannot demote itself.
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('edit', $this->admin->id)->set('role', 'pimpinan')->call('save')->assertHasErrors('role');
        $this->assertSame(Role::Admin, $this->admin->fresh()->role);
    }

    public function test_reset_rotates_remember_token_and_admin_cannot_reset_self(): void
    {
        $u = User::factory()->pimpinan()->create(['remember_token' => 'lama-lama']);
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('confirmReset', $u->id)
            ->set('sementara', 'Sementara1')->call('aturUlang')->assertHasNoErrors()->assertSet('sementara', '');
        $this->assertNotSame('lama-lama', $u->fresh()->remember_token);

        $hash = $this->admin->password;
        Livewire::actingAs($this->admin)->test(Pengguna::class)->call('confirmReset', $this->admin->id)
            ->assertSet('resetOpen', false)->assertDispatched('toast', type: 'error', message: 'Ubah kata sandi Anda sendiri lewat halaman Profil.');
        $this->assertSame($hash, $this->admin->fresh()->password);
        $this->expectException(ValidationException::class);
        app(AturUlangKataSandi::class)->handle($this->admin, $this->admin, 'Sementara1');
    }

    public function test_non_admin_forbidden(): void
    {
        $u = User::factory()->pimpinan()->create();
        $target = User::factory()->irban()->create();
        $this->actingAs($u)->get('/admin/pengguna')->assertForbidden();
        Livewire::actingAs($u)->test(Pengguna::class)->assertForbidden();

        // Direct calls on a component mounted by an admin, then the session user swapped to a non-admin.
        foreach ([['create'], ['edit', $target->id], ['confirmReset', $target->id], ['save'], ['aturUlang']] as $call) {
            $c = Livewire::actingAs($this->admin)->test(Pengguna::class);
            $this->actingAs($u);
            $c->call(...$call)->assertForbidden();
        }
    }
}
