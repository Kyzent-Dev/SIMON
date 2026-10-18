<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Fase2SmallFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privileged_columns_are_not_mass_assignable(): void
    {
        $user = User::create(['name' => 'X', 'username' => 'x', 'email' => 'x@t.test', 'password' => 'secret123',
            'role' => Role::Admin, 'irban_id' => 99, 'wajib_ganti_password' => true]);
        $user->refresh();

        $this->assertSame(Role::Irban, $user->role); // DB default, not Admin
        $this->assertNull($user->irban_id);
        $this->assertFalse((bool) $user->wajib_ganti_password);
    }

    public function test_flagged_user_is_redirected_from_every_authenticated_route(): void
    {
        $user = User::factory()->admin()->create(['wajib_ganti_password' => true]);
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            if (! in_array('auth', $route->gatherMiddleware(), true) || ! in_array('GET', $route->methods(), true)
                || str_contains($route->uri(), '{') || $route->named('password.force.*', 'logout')) {
                continue;
            }
            $this->actingAs($user)->get('/'.ltrim($route->uri(), '/'))->assertRedirect('/perbarui-kata-sandi');
            $checked++;
        }

        $this->assertGreaterThanOrEqual(5, $checked);
        $this->actingAs($user)->get('/perbarui-kata-sandi')->assertOk();
    }

    public function test_reset_password_post_is_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/atur-ulang-kata-sandi', [])->assertStatus(302);
        }
        $this->post('/atur-ulang-kata-sandi', [])->assertStatus(429);
    }

    public function test_initials(): void
    {
        $this->assertSame('BS', (new User(['name' => 'budi  santoso wijaya']))->initials());
        $this->assertSame('Ä', (new User(['name' => 'ärni']))->initials());
        $this->assertSame('', (new User(['name' => '  ']))->initials());
    }

    public function test_login_shows_lockout_after_reload_only_with_old_username(): void
    {
        $this->withoutVite();
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit('bob|127.0.0.1', 60);
        }

        $this->withSession(['_old_input' => ['username' => 'Bob']])->get('/masuk')
            ->assertOk()->assertSee('x-text="s"', false);
        $this->flushSession()->get('/masuk')->assertOk()->assertDontSee('x-text="s"', false);
    }
}
