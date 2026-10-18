<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\RequireIrbanPasswordChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function flagged(string $state = 'irban'): User
    {
        return User::factory()->{$state}()->create(['wajib_ganti_password' => true]);
    }

    public function test_flagged_user_is_redirected_from_every_dashboard(): void
    {
        $user = $this->flagged();
        foreach (['/dashboard', '/admin', '/pimpinan', '/irban', '/bagian-umum'] as $path) {
            $this->actingAs($user)->get($path)->assertRedirect('/perbarui-kata-sandi');
        }
    }

    public function test_login_of_flagged_user_ends_on_force_page(): void
    {
        $user = $this->flagged();
        $this->post('/masuk', ['username' => $user->username, 'password' => 'password'])->assertRedirect('/irban');
        $this->get('/irban')->assertRedirect('/perbarui-kata-sandi');
    }

    public function test_force_page_renders_without_navigation(): void
    {
        $year = now('Asia/Jakarta')->year;
        $this->actingAs($this->flagged())->get('/perbarui-kata-sandi')->assertOk()
            ->assertSee('Perbarui Kata Sandi')
            ->assertSee("Administrator mewajibkan pembaruan kata sandi untuk tahun {$year}.")
            ->assertSee('Minimal 8 karakter')->assertSee('Huruf besar dan kecil')
            ->assertSee('Angka')->assertSee('Berbeda dari kata sandi lama')
            ->assertSee('Lemah')->assertSee('Sangat kuat')
            ->assertSee('Simpan &amp; Lanjutkan', false)
            ->assertDontSee('<nav', false);
    }

    public function test_unflagged_user_cannot_open_force_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/perbarui-kata-sandi')->assertRedirect('/dashboard');
    }

    public function test_flagged_user_can_log_out(): void
    {
        $this->actingAs($this->flagged())->post('/keluar')->assertRedirect('/masuk');
        $this->assertGuest();
    }

    public function test_old_password_cannot_be_reused(): void
    {
        $user = User::factory()->create(['wajib_ganti_password' => true, 'password' => 'LamaSekali1']);
        $this->actingAs($user)->put('/perbarui-kata-sandi', ['password' => 'LamaSekali1', 'password_confirmation' => 'LamaSekali1'])
            ->assertSessionHasErrors(['password' => 'Kata sandi baru harus berbeda dari kata sandi lama.']);
        $this->assertTrue($user->fresh()->wajib_ganti_password);
    }

    public function test_each_weak_rule_fails(): void
    {
        $user = $this->flagged();
        foreach (['Ab1', 'semuakecil1', 'SEMUABESAR1', 'TanpaAngka'] as $weak) {
            $this->actingAs($user)->put('/perbarui-kata-sandi', ['password' => $weak, 'password_confirmation' => $weak])
                ->assertSessionHasErrors('password');
        }
        $this->actingAs($user)->put('/perbarui-kata-sandi', ['password' => 'BaruSekali1', 'password_confirmation' => 'Berbeda1'])
            ->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->wajib_ganti_password);
    }

    public function test_success_clears_flag_and_redirects_to_role_dashboard(): void
    {
        $user = $this->flagged('pimpinan');
        $this->actingAs($user)->put('/perbarui-kata-sandi', ['password' => 'BaruSekali1', 'password_confirmation' => 'BaruSekali1'])
            ->assertRedirect('/pimpinan');
        $user->refresh();
        $this->assertFalse($user->wajib_ganti_password);
        $this->assertTrue(Hash::check('BaruSekali1', $user->password));
        $this->actingAs($user)->get('/pimpinan')->assertOk();
    }

    public function test_require_irban_password_change_flags_only_irban(): void
    {
        $irbans = User::factory()->irban()->count(3)->create();
        $others = collect([User::factory()->admin()->create(), User::factory()->pimpinan()->create(), User::factory()->bagianUmum()->create()]);

        $this->assertSame(3, app(RequireIrbanPasswordChange::class)());

        $irbans->each(fn ($u) => $this->assertTrue($u->fresh()->wajib_ganti_password));
        $others->each(fn ($u) => $this->assertFalse($u->fresh()->wajib_ganti_password));
    }
}
