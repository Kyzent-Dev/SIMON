<?php

namespace Tests\Feature\Keamanan;

use App\Enums\Role;
use App\Livewire\BagianUmum\Register;
use App\Livewire\Pimpinan\Monitoring;
use App\Livewire\Profil;
use App\Models\Ekspor as EksporModel;
use App\Models\User;
use App\Notifications\VerifikasiEmailBaru;
use App\Services\Ekspor;
use App\Services\Ekspor\PembangunMonitoring;
use App\Services\Ekspor\PembangunRegister;
use App\Services\MonitoringSpt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 11 Task 2 (E1): public pages 120/min per IP, export 10/min, email change 3 per 10 min, login stays 5. */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_publik_dibatasi(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->get('/jadwal')->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.1'])->get('/jadwal')
            ->assertStatus(429)->assertSee('Terlalu banyak permintaan');
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.1.2'])->get('/jadwal')->assertOk();
    }

    public function test_ekspor_dibatasi(): void
    {
        Queue::fake();
        $this->app->bind(PembangunRegister::class, fn () => new class extends PembangunRegister
        {
            public function jumlah(User $u, array $f): int
            {
                return Ekspor::BATAS_LANGSUNG + 1;
            }
        });
        $c = Livewire::actingAs(User::factory()->create(['role' => Role::BagianUmum]))->test(Register::class);
        for ($i = 0; $i < 10; $i++) {
            $c->call('ekspor', 'pdf');
        }
        $this->assertSame(10, EksporModel::count());

        $c->call('ekspor', 'pdf')->assertDispatched('toast', type: 'error');
        $this->assertSame(10, EksporModel::count());
    }

    public function test_ekspor_monitoring_dibatasi(): void
    {
        Queue::fake();
        $this->app->bind(PembangunMonitoring::class, fn ($app) => new class($app->make(MonitoringSpt::class)) extends PembangunMonitoring
        {
            public function jumlah(User $u, array $f): int
            {
                return Ekspor::BATAS_LANGSUNG + 1;
            }
        });
        $c = Livewire::actingAs(User::factory()->pimpinan()->create())->test(Monitoring::class);
        for ($i = 0; $i < 10; $i++) {
            $c->call('ekspor', 'xlsx');
        }
        $this->assertSame(10, EksporModel::count());

        $c->call('ekspor', 'xlsx')->assertDispatched('toast', type: 'error');
        $this->assertSame(10, EksporModel::count());
    }

    /** The quota counts only requests that passed authorization: a refused request neither spends it nor turns into "too many requests". */
    public function test_ekspor_ditolak_otorisasi_tidak_memakan_kuota(): void
    {
        $irban = User::factory()->irban()->create();
        for ($i = 0; $i < 12; $i++) {
            try {
                app(Ekspor::class)->minta($irban, 'register', 'xlsx', []);
                $this->fail('Irban tidak boleh mengekspor register.');
            } catch (AuthorizationException) {
                $this->assertSame(0, RateLimiter::attempts('ekspor:'.$irban->id));
            }
        }
    }

    public function test_ganti_email_dibatasi(): void
    {
        Notification::fake();
        $user = User::factory()->pimpinan()->create(['email' => 'lama@example.test']);
        $c = Livewire::actingAs($user)->test(Profil::class);
        foreach (['a', 'b', 'c'] as $n) {
            $c->set('email', "$n@example.test")->call('simpanAkun')->assertHasNoErrors();
        }
        Notification::assertSentOnDemandTimes(VerifikasiEmailBaru::class, 3);

        $c->set('email', 'd@example.test')->call('simpanAkun')->assertHasErrors('email');
        $this->assertStringContainsString('Terlalu banyak permintaan. Coba lagi dalam', $c->errors()->first('email'));
        Notification::assertSentOnDemandTimes(VerifikasiEmailBaru::class, 3);
    }

    public function test_login_tetap_5_kali(): void
    {
        User::factory()->create(['username' => 'budi']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/masuk', ['username' => 'budi', 'password' => 'salah'])->assertSessionHasErrors('login');
        }
        $this->post('/masuk', ['username' => 'budi', 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertStringContainsString('Terlalu banyak percobaan masuk', session('errors')->first('login'));
        $this->assertGuest();
    }
}
