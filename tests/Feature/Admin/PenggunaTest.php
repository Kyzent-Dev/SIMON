<?php

namespace Tests\Feature\Admin;

use App\Actions\Pengguna\SimpanPengguna;
use App\Enums\Role;
use App\Livewire\Admin\Pengguna;
use App\Models\Irban;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        // The actor is another admin, so the self-rule does not apply; only the last-admin rule can refuse.
        $this->admin->delete();
        $solo = User::factory()->admin()->create();
        $this->expectException(ValidationException::class);
        app(SimpanPengguna::class)->handle(User::factory()->admin()->make(['id' => 99999]), $solo, [
            'name' => 'x', 'username' => 'abc', 'email' => 'a@b.test', 'role' => 'pimpinan', 'irban_id' => null,
        ]);
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
