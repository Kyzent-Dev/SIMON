<?php

namespace Tests\Feature\Publik;

use App\Models\LandingSlide;
use App\Models\User;
use App\Services\Publik\RealisasiPublik;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class BerandaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00', 'Asia/Jakarta'));
        $this->withoutVite();
    }

    private function html(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_home_is_public_and_also_works_logged_in(): void
    {
        $this->get(route('beranda'))->assertOk()->assertSee('aria-label="Masuk"', false)->assertSee('href="'.route('login').'"', false)
            ->assertDontSee('aria-label="Dasbor"', false);

        $this->actingAs(User::factory()->admin()->create())->get('/')->assertOk()
            ->assertSee('aria-label="Dasbor"', false)->assertSee('href="'.route('dashboard').'"', false)
            ->assertDontSee('aria-label="Masuk"', false);
    }

    public function test_layout_landmarks_skip_link_nav_and_single_h1(): void
    {
        $html = $this->html();
        $this->assertStringContainsString('<html lang="id" class="scroll-pt-14 md:scroll-pt-16">', $html);
        $this->assertStringContainsString('href="#konten"', $html);
        $this->assertStringContainsString('Lewati ke konten utama', $html);
        $this->assertMatchesRegularExpression('/<main id="konten" tabindex="-1"/', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        // Home is current in both the desktop and the mobile nav; Jadwal/Realisasi are not.
        $this->assertSame(2, preg_match_all('/<a[^>]*href="'.preg_quote(url('/'), '/').'"[^>]*aria-current="page"/', $html));
        $this->assertSame(0, preg_match_all('/<a[^>]*href="'.preg_quote(url('/jadwal'), '/').'"[^>]*aria-current/', $html));
        $this->assertStringContainsString('aria-controls="menu-publik"', $html);
        $this->assertStringContainsString('Buka menu', $html);
        $this->assertStringContainsString('<footer', $html);
    }

    public function test_slides_in_order_with_srcset_priority_and_alt(): void
    {
        $b = LandingSlide::factory()->create(['judul' => 'Slide Kedua', 'urutan' => 2, 'gambar' => 'slide/bbb', 'dekoratif' => true, 'alt' => null]);
        LandingSlide::factory()->create(['judul' => 'Slide Nonaktif', 'urutan' => 1, 'aktif' => false]);
        $a = LandingSlide::factory()->create(['judul' => 'Slide Pertama', 'urutan' => 0, 'gambar' => 'slide/aaa', 'alt' => 'Foto kegiatan',
            'kutipan' => 'Kutipan pertama', 'pemilik_nama' => 'Budi', 'pemilik_jabatan' => 'Inspektur']);

        $html = $this->html();
        $this->assertStringNotContainsString('Slide Nonaktif', $html);
        $this->assertLessThan(strpos($html, 'Slide Kedua'), strpos($html, 'Slide Pertama'));
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Slide Pertama/', $html);
        $this->assertMatchesRegularExpression('/<p[^>]*>\s*Slide Kedua/', $html);
        $this->assertStringContainsString('srcset="'.$a->gambarUrl(960).' 960w, '.$a->gambarUrl(1920).' 1920w"', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*slide\/aaa-1920\.webp"[^>]*alt="Foto kegiatan"[^>]*fetchpriority="high"/', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*slide\/bbb-1920\.webp"[^>]*alt=""[^>]*loading="lazy"/', $html);
        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertMatchesRegularExpression('/<blockquote[^>]*>\s*<p[^>]*>Kutipan pertama<\/p>\s*<\/blockquote>\s*<figcaption[^>]*>— Budi, Inspektur<\/figcaption>/', $html);
        $this->assertStringContainsString('aria-roledescription="carousel"', $html);
        $this->assertStringContainsString('aria-label="1 dari 2"', $html);
        $this->assertStringContainsString('x-data="carousel({ count: 2, interval: 7000 })"', $html);
        foreach (['Jeda slide', 'Slide sebelumnya', 'Slide berikutnya', 'Slide 1 dari 2', 'Slide 2 dari 2'] as $label) {
            $this->assertStringContainsString($label, $html);
        }
        // Pause/Putar is the first control in tab order.
        $this->assertLessThan(strpos($html, 'Slide sebelumnya'), strpos($html, 'Jeda slide'));
        $this->assertLessThan(strpos($html, 'Slide 1 dari 2'), strpos($html, 'Jeda slide'));
        unset($b);
    }

    public function test_one_slide_has_no_controls(): void
    {
        LandingSlide::factory()->create(['judul' => 'Satu-satunya']);
        $html = $this->html();
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Satu-satunya/', $html);
        $this->assertStringNotContainsString('Jeda slide', $html);
        $this->assertStringNotContainsString('Slide berikutnya', $html);
        $this->assertStringNotContainsString('x-data="carousel(', $html);
    }

    public function test_no_slide_shows_brand_hero(): void
    {
        LandingSlide::factory()->create(['aktif' => false]);
        $html = $this->html();
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Mengawal akuntabilitas pemerintahan Kabupaten Muara Enim/', $html);
        $this->assertStringContainsString('INSPEKTORAT DAERAH', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_no_disahkan_pkpt_shows_zero_copy(): void
    {
        $this->get('/')->assertSee('Belum ada PKPT yang disahkan untuk 2026.')
            ->assertDontSee('Persentase Realisasi')->assertSee('Data per 20 Oktober 2026');
    }

    public function test_failing_stats_show_dash_and_caption(): void
    {
        $this->mock(RealisasiPublik::class)->shouldReceive('ringkasan')->andThrow(new RuntimeException('db down'));
        $this->get('/')->assertOk()->assertSee('Data belum tersedia')->assertSee('Item PKPT 2026')->assertSee('—')
            ->assertDontSee('Data per 20 Oktober 2026')->assertDontSee('db down');
    }

    public function test_link_cards(): void
    {
        $html = $this->html();
        foreach (['Jadwal PKPT', 'Lihat siapa memeriksa apa dan kapan, sepanjang tahun.', 'Buka jadwal →', 'Realisasi Pengawasan',
            'Lihat berapa banyak rencana pengawasan yang sudah terlaksana.', 'Lihat realisasi →'] as $t) {
            $this->assertStringContainsString($t, $html);
        }
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(url('/jadwal'), '/').'"[^>]*rounded-xl/', $html);
    }

    public function test_footer_renders_only_filled_columns_and_no_initial_map(): void
    {
        app(Settings::class)->saveGroup('kontak', ['kontak' => ['telepon' => '(0734) 421001', 'email' => '', 'instagram' => 'inspektorat.me',
            'alamat' => '', 'peta_url' => 'https://www.google.com/maps/embed?pb=x']]);

        $html = $this->html();
        $this->assertStringContainsString('href="tel:0734421001"', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringContainsString('href="https://www.instagram.com/inspektorat.me"', $html);
        $this->assertStringContainsString('@inspektorat.me', $html);
        $this->assertStringNotContainsString('Alamat Kantor', $html);
        $this->assertStringContainsString('Tampilkan peta', $html);
        $this->assertDoesNotMatchRegularExpression('/<iframe(?![^>]*x-ref)/', $html); // only the x-if template, never a live iframe
        $this->assertStringContainsString('<template x-if="peta">', $html);
    }
}
