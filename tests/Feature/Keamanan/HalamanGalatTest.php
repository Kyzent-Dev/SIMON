<?php

namespace Tests\Feature\Keamanan;

use App\Models\Aktivitas;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Fase 11 Task 2 (E3, E4): error pages on the auth shell, security headers, trusted proxies. */
class HalamanGalatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    public static function halaman(): array
    {
        return [
            [403, 'Akses ditolak', 'Kembali ke beranda'],
            [404, 'Halaman tidak ditemukan', 'Kembali ke beranda'],
            [500, 'Terjadi kesalahan', 'Kembali ke beranda'],
            [503, 'Sedang dalam pemeliharaan', null],
        ];
    }

    #[DataProvider('halaman')]
    public function test_halaman_galat(int $kode, string $judul, ?string $tombol): void
    {
        Route::middleware('web')->get('/_uji-galat', fn () => abort($kode));

        $r = $this->get('/_uji-galat')->assertStatus($kode)
            ->assertSee($judul)->assertSee('<title>'.$judul.' · SINTAS</title>', false)->assertSee('lang="id"', false);
        $tombol ? $r->assertSee($tombol)->assertSee('href="'.url('/').'"', false) : $r->assertDontSee('Kembali ke beranda');
    }

    public function test_tombol_masuk_ke_dasbor_saat_login(): void
    {
        Route::middleware('web')->get('/_uji-galat', fn () => abort(404));

        $this->actingAs(User::factory()->create())->get('/_uji-galat')->assertNotFound()->assertSee('href="'.route('dashboard').'"', false);
    }

    public function test_419_dan_muat_ulang(): void
    {
        $this->assertStringContainsString('Sesi kedaluwarsa', view('errors.419')->render());
        $this->assertStringContainsString('Muat ulang', view('errors.419')->render());
    }

    /** The public pages and beranda share throttle:publik, so a link home would hit the same limit: reload after waiting instead. */
    public function test_429_muat_ulang_tanpa_tautan_beranda(): void
    {
        Route::middleware('web')->get('/_uji-galat', fn () => abort(429));

        $this->get('/_uji-galat')->assertStatus(429)->assertSee('<title>Terlalu banyak permintaan · SINTAS</title>', false)
            ->assertSee('Tunggu sebentar sebelum mencoba lagi.')->assertSee('onclick="location.reload()"', false)->assertSee('Muat ulang')
            ->assertDontSee('Kembali ke beranda');
    }

    public function test_500_tanpa_database(): void
    {
        // No web group: the session would try to save on the broken connection, which is not what this page is about.
        Route::get('/_uji-galat', function () {
            config(['database.default' => 'tidak_ada']);
            abort(500);
        });

        $asli = config('database.default');
        try {
            $this->get('/_uji-galat')->assertStatus(500)->assertSee('Terjadi kesalahan');
        } finally {
            config(['database.default' => $asli]); // RefreshDatabase rolls back on the default connection
        }
    }

    public function test_header_keamanan(): void
    {
        foreach (['/', '/masuk'] as $url) {
            $this->get($url)->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        }
    }

    public function test_trusted_proxies(): void
    {
        $uji = function () {
            Route::middleware('web')->get('/_uji-ip', function () {
                activity()->log('uji ip');

                return 'ok';
            });
            $this->get('/_uji-ip', ['X-Forwarded-For' => '203.0.113.9'])->assertOk();

            return Aktivitas::latest('id')->value('ip');
        };

        $this->assertSame('127.0.0.1', $uji());

        // Proxies are read from config in the provider's boot, so run it again after setting the config.
        config(['app.trusted_proxies' => '*']);
        (new AppServiceProvider($this->app))->boot();
        $this->assertSame('203.0.113.9', $uji());
    }
}
