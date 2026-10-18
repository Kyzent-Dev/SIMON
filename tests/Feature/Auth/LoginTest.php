<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_login_page_renders_fields_from_design(): void
    {
        $this->get('/masuk')->assertOk()
            ->assertSee('Lewati ke konten utama')
            ->assertSee('Nama pengguna')
            ->assertSee('autocomplete="username"', false)
            ->assertSee('Kata sandi')
            ->assertSee('Tampilkan kata sandi')
            ->assertSee('aria-pressed', false)
            ->assertSee('Ingat saya')
            ->assertSee('Lupa kata sandi?')
            ->assertSee('<main id="konten"', false);
    }

    public function test_each_role_lands_on_own_dashboard(): void
    {
        $paths = ['admin' => '/admin', 'pimpinan' => '/pimpinan', 'irban' => '/irban', 'bagianUmum' => '/bagian-umum'];
        foreach ($paths as $state => $path) {
            $user = User::factory()->{$state}()->create();
            $this->post('/masuk', ['username' => $user->username, 'password' => 'password'])
                ->assertRedirect($path);
            $this->assertAuthenticatedAs($user);
            $this->post('/keluar');
        }
    }

    public function test_remember_me_sets_remember_token_cookie(): void
    {
        $user = User::factory()->create();
        $response = $this->post('/masuk', ['username' => $user->username, 'password' => 'password', 'remember' => '1']);
        $this->assertNotEmpty(collect($response->headers->getCookies())->filter(fn ($c) => str_starts_with($c->getName(), 'remember_web_')));
    }

    public function test_wrong_password_shows_generic_message_only(): void
    {
        $user = User::factory()->create();
        $this->from('/masuk')->post('/masuk', ['username' => $user->username, 'password' => 'salah'])
            ->assertRedirect('/masuk')
            ->assertSessionHasErrors(['login' => 'Nama pengguna atau kata sandi salah.'])
            ->assertSessionDoesntHaveErrors(['username', 'password']);
        $this->assertGuest();

        // assertSessionHasErrors consumes the flashed bag, so render after a fresh failed attempt.
        $this->from('/masuk')->post('/masuk', ['username' => $user->username, 'password' => 'salah']);
        $this->get('/masuk')->assertSee('Nama pengguna atau kata sandi salah.')->assertSee('role="alert"', false);
    }

    public function test_sixth_attempt_is_locked_out_even_with_correct_password(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['username' => $user->username, 'password' => 'salah']);
        }

        $response = $this->from('/masuk')->post('/masuk', ['username' => strtoupper($user->username), 'password' => 'password']);
        $response->assertRedirect('/masuk')->assertSessionHasErrors('login');
        $this->assertMatchesRegularExpression(
            '/^Terlalu banyak percobaan masuk\. Coba lagi dalam \d+ detik\.$/',
            session('errors')->first('login'),
        );
        $this->assertGuest();

        $this->get('/masuk')->assertSee('Terlalu banyak percobaan masuk.')->assertSee('aria-live="polite"', false);
    }

    public function test_logout_invalidates_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/keluar')->assertRedirect('/masuk');
        $this->assertGuest();
        $this->get('/irban')->assertRedirect('/masuk');
    }

    public function test_authenticated_user_visiting_login_is_redirected(): void
    {
        $this->actingAs(User::factory()->create())->get('/masuk')->assertRedirect('/dashboard');
    }

    public function test_expired_csrf_redirects_to_login_with_message(): void
    {
        // The real CSRF middleware is skipped under unit tests, so throw its exception from a web route.
        Route::middleware('web')->post('/_uji-csrf', fn () => throw new TokenMismatchException);

        $this->post('/_uji-csrf')->assertRedirect('/masuk');
        $this->get('/masuk')->assertSee('Sesi berakhir, silakan masuk kembali.');
    }
}
