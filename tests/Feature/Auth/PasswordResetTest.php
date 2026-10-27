<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const SENT = 'Jika email terdaftar, tautan reset telah dikirim. Periksa kotak masuk Anda.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_forgot_page_renders(): void
    {
        $this->get('/lupa-kata-sandi')->assertOk()
            ->assertSee('Email')->assertSee('Kirim tautan reset')->assertSee('Kembali ke halaman masuk');
    }

    public function test_neutral_message_for_existing_and_unknown_email(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->from('/lupa-kata-sandi')->post('/lupa-kata-sandi', ['email' => $user->email])
            ->assertRedirect('/lupa-kata-sandi')->assertSessionHas('status', self::SENT);
        $this->from('/lupa-kata-sandi')->post('/lupa-kata-sandi', ['email' => 'tidak-ada@sintas.test'])
            ->assertRedirect('/lupa-kata-sandi')->assertSessionHas('status', self::SENT)->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertSentTimes(ResetPassword::class, 1);

        $this->get('/lupa-kata-sandi')->assertSee(self::SENT);
    }

    public function test_reset_link_points_to_indonesian_route(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/lupa-kata-sandi', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($n) use ($user) {
            $url = $n->toMail($user)->actionUrl;

            return str_contains($url, '/atur-ulang-kata-sandi/'.$n->token);
        });
    }

    public function test_reset_page_renders(): void
    {
        $this->get('/atur-ulang-kata-sandi/abc?email=a@b.test')->assertOk()->assertSee('value="abc"', false);
    }

    public function test_valid_token_changes_password(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $this->post('/atur-ulang-kata-sandi', [
            'token' => $token, 'email' => $user->email,
            'password' => 'BaruSekali1', 'password_confirmation' => 'BaruSekali1',
        ])->assertRedirect('/masuk')->assertSessionHas('status');

        $this->assertTrue(Hash::check('BaruSekali1', $user->fresh()->password));
    }

    public function test_reset_rejects_weak_password_and_bad_token(): void
    {
        $user = User::factory()->create();
        $this->post('/atur-ulang-kata-sandi', [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'lemah', 'password_confirmation' => 'lemah',
        ])->assertSessionHasErrors('password');

        $this->post('/atur-ulang-kata-sandi', [
            'token' => 'salah', 'email' => $user->email,
            'password' => 'BaruSekali1', 'password_confirmation' => 'BaruSekali1',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_failed_reset_does_not_reveal_whether_email_exists(): void
    {
        $user = User::factory()->create();
        $payload = fn (string $email) => [
            'token' => 'token-acak-'.$email, 'email' => $email,
            'password' => 'BaruSekali1', 'password_confirmation' => 'BaruSekali1',
        ];

        $this->post('/atur-ulang-kata-sandi', $payload('tidak-ada@sintas.test'))->assertSessionHasErrors('email');
        $unknownMessage = session('errors')->first('email');
        $this->post('/atur-ulang-kata-sandi', $payload($user->email))->assertSessionHasErrors('email');
        $knownMessage = session('errors')->first('email');

        $this->assertSame($unknownMessage, $knownMessage);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->assertSame(1, User::count());
    }
}
