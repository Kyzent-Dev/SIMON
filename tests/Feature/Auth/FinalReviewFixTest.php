<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RequireIrbanPasswordChange;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FinalReviewFixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_unflagged_user_cannot_put_forced_password(): void
    {
        $user = User::factory()->irban()->create();
        $hash = $user->password;

        $this->actingAs($user)->put('/perbarui-kata-sandi', ['password' => 'BaruSekali1', 'password_confirmation' => 'BaruSekali1'])
            ->assertRedirect('/dashboard');

        $this->assertSame($hash, $user->fresh()->password);
    }

    public function test_forced_change_rotates_remember_token(): void
    {
        $user = User::factory()->irban()->create(['wajib_ganti_password' => true, 'remember_token' => 'lama']);

        $this->actingAs($user)->put('/perbarui-kata-sandi', ['password' => 'BaruSekali1', 'password_confirmation' => 'BaruSekali1']);

        $this->assertNotSame('lama', $user->fresh()->remember_token);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_flagging_irban_clears_remember_token(): void
    {
        $user = User::factory()->irban()->create(['remember_token' => 'lama']);

        app(RequireIrbanPasswordChange::class)();

        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_reset_mail_is_indonesian(): void
    {
        $user = User::factory()->admin()->create();
        $mail = (new ResetPassword('tok'))->toMail($user);

        $this->assertSame('Atur ulang kata sandi Anda', $mail->subject);
        $this->assertStringContainsString('Atur Ulang Kata Sandi', $mail->actionText);
        $this->assertStringContainsString('60 menit', implode(' ', $mail->outroLines));
        $this->assertSame('Halo!', __('Hello!'));
    }

    public function test_forgot_password_is_throttled(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/lupa-kata-sandi', ['email' => 'a@simon-spt.test'])->assertStatus(302);
        }
        $this->post('/lupa-kata-sandi', ['email' => 'a@simon-spt.test'])->assertStatus(429);
    }

    public function test_intended_url_of_other_role_is_ignored(): void
    {
        $user = User::factory()->irban()->create();

        $this->get('/admin')->assertRedirect('/masuk');
        $this->post('/masuk', ['username' => $user->username, 'password' => 'password'])->assertRedirect('/irban');
    }

    public function test_user_seeder_does_nothing_in_production(): void
    {
        $this->app['env'] = 'production';

        app(UserSeeder::class)->run();

        $this->assertSame(0, User::count());
    }
}
