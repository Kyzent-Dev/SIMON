<?php

namespace Tests\Feature\Konten;

use App\Actions\Konten\UrutkanSlide;
use App\Enums\Role;
use App\Livewire\Admin\Dasbor;
use App\Livewire\Admin\Slide as Page;
use App\Models\LandingSlide;
use App\Models\User;
use App\Services\Settings;
use Database\Seeders\LandingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class SlideTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Page::class);
    }

    private function isi($c, string $judul = 'Pengawasan Desa')
    {
        return $c->call('create')->set('judul', $judul)->set('alt', 'Tim memeriksa dokumen')
            ->set('gambar', UploadedFile::fake()->image('a.jpg', 2400, 1600));
    }

    private function ukuran(string $path): array
    {
        $info = getimagesize(Storage::disk('public')->path($path));

        return [$info[0], $info[1], $info['mime']];
    }

    public function test_only_admin_can_open_page(): void
    {
        $this->get('/admin/slide')->assertRedirect('/masuk');
        foreach (['pimpinan', 'irban', 'bagianUmum'] as $role) {
            $this->actingAs(User::factory()->$role()->create())->get('/admin/slide')->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create())->get('/admin/slide')->assertOk()->assertSee('Slide Beranda');
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();
    }

    public function test_every_action_is_authorized(): void
    {
        $s = LandingSlide::factory()->create();
        LandingSlide::factory()->create();
        $calls = [['create'], ['edit', $s->id], ['save'], ['naikkan', $s->id], ['turunkan', $s->id], ['pindahkan', $s->id, 1], ['aturAktif', $s->id, false]];
        foreach ($calls as $call) {
            $method = array_shift($call);
            $args = $call;
            $admin = User::factory()->admin()->create();
            $c = Livewire::actingAs($admin)->test(Page::class);
            $admin->role = Role::Irban;
            $c->call($method, ...$args)->assertForbidden();
        }
    }

    public function test_create_writes_two_webp_variants_at_16_9_and_appends_urutan(): void
    {
        LandingSlide::factory()->create(['urutan' => 4]);
        $this->isi($this->page())->set('kutipan', 'Kutipan')->call('save')->assertHasNoErrors()->assertDispatched('toast', message: 'Slide disimpan.');

        $s = LandingSlide::where('judul', 'Pengawasan Desa')->firstOrFail();
        $this->assertSame(5, $s->urutan); // max 4 + 1
        $this->assertMatchesRegularExpression('#^slide/[0-9a-f-]{36}$#', $s->gambar);
        // 2400x1600 is cropped to 2400x1350 (16:9), then scaled to 1920x1080 and 960x540.
        $this->assertSame([1920, 1080, 'image/webp'], $this->ukuran($s->gambar.'-1920.webp'));
        $this->assertSame([960, 540, 'image/webp'], $this->ukuran($s->gambar.'-960.webp'));
        $this->assertLessThanOrEqual(250 * 1024, Storage::disk('public')->size($s->gambar.'-1920.webp'));
        $this->assertTrue($s->aktif);
    }

    public function test_square_image_is_center_cropped_to_16_9(): void
    {
        $this->page()->call('create')->set('judul', 'Persegi')->set('alt', 'x')
            ->set('gambar', UploadedFile::fake()->image('b.png', 3000, 3000))->call('save')->assertHasNoErrors();
        $s = LandingSlide::firstOrFail();
        $this->assertSame([1920, 1080, 'image/webp'], $this->ukuran($s->gambar.'-1920.webp'));
        $this->assertSame([960, 540, 'image/webp'], $this->ukuran($s->gambar.'-960.webp'));
    }

    public function test_small_source_is_not_upscaled(): void
    {
        $this->page()->call('create')->set('judul', 'Kecil')->set('alt', 'x')
            ->set('gambar', UploadedFile::fake()->image('c.jpg', 1200, 675))->call('save')->assertHasNoErrors();
        $s = LandingSlide::firstOrFail();
        $this->assertSame([1200, 675, 'image/webp'], $this->ukuran($s->gambar.'-1920.webp'));
    }

    public function test_image_rules(): void
    {
        $this->page()->call('create')->set('judul', 'A')->set('alt', 'x')->call('save')
            ->assertHasErrors(['gambar'])->assertSee('Gambar wajib diunggah.');
        $this->page()->call('create')->set('judul', 'A')->set('alt', 'x')
            ->set('gambar', UploadedFile::fake()->image('a.jpg', 959, 600))->call('save')
            ->assertHasErrors(['gambar'])->assertSee('Lebar gambar minimal 960 piksel.');
        $this->page()->call('create')->set('judul', 'A')->set('alt', 'x')
            ->set('gambar', UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'))->call('save')
            ->assertHasErrors(['gambar'])->assertSee('Gambar harus JPG, PNG, atau WebP.');
        $this->page()->call('create')->set('judul', 'A')->set('alt', 'x')
            ->set('gambar', UploadedFile::fake()->image('a.jpg', 2000, 1200)->size(6000))->call('save')
            ->assertHasErrors(['gambar'])->assertSee('Ukuran gambar slide maksimal 5120 KB.');
        $this->assertSame(0, LandingSlide::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_judul_and_alt_rules(): void
    {
        $this->isi($this->page(), '')->call('save')->assertHasErrors(['judul'])->assertSee('Judul wajib diisi.');
        $this->isi($this->page())->set('alt', '')->call('save')->assertHasErrors(['alt'])
            ->assertSee('Teks alternatif gambar wajib diisi, atau tandai gambar sebagai dekoratif.');
        $this->isi($this->page())->set('judul', str_repeat('a', 81))->call('save')->assertHasErrors(['judul']);
        $this->isi($this->page())->set('kutipan', str_repeat('a', 201))->call('save')->assertHasErrors(['kutipan']);

        $this->isi($this->page())->set('alt', 'sisa')->set('dekoratif', true)->call('save')->assertHasNoErrors();
        $s = LandingSlide::firstOrFail();
        $this->assertTrue($s->dekoratif);
        $this->assertNull($s->alt);
    }

    public function test_update_without_image_keeps_files_and_with_image_replaces_them(): void
    {
        $this->isi($this->page())->call('save');
        $s = LandingSlide::firstOrFail();
        $lama = $s->gambar;

        $this->page()->call('edit', $s->id)->assertSet('judul', 'Pengawasan Desa')->set('judul', 'Baru')->call('save')->assertHasNoErrors();
        $this->assertSame('Baru', $s->fresh()->judul);
        $this->assertSame($lama, $s->fresh()->gambar);
        Storage::disk('public')->assertExists($lama.'-1920.webp');

        $this->page()->call('edit', $s->id)->set('gambar', UploadedFile::fake()->image('n.jpg', 1920, 1080))->call('save')->assertHasNoErrors();
        $baru = $s->fresh()->gambar;
        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing($lama.'-1920.webp');
        Storage::disk('public')->assertMissing($lama.'-960.webp');
        Storage::disk('public')->assertExists($baru.'-1920.webp');
        Storage::disk('public')->assertExists($baru.'-960.webp');
        $this->assertSame(1, $s->fresh()->urutan); // editing keeps the position
    }

    public function test_urutkan_reorders_and_rejects_foreign_or_missing_ids(): void
    {
        [$a, $b, $c] = LandingSlide::factory()->count(3)->sequence(['urutan' => 1], ['urutan' => 2], ['urutan' => 3])->create();

        $this->page()->call('pindahkan', $c->id, 0)->assertHasNoErrors()->assertDispatched('toast', message: 'Urutan slide disimpan.');
        $this->assertSame([$c->id, $a->id, $b->id], LandingSlide::orderBy('urutan')->pluck('id')->all());
        $this->assertSame([1, 2, 3], LandingSlide::orderBy('urutan')->pluck('urutan')->all());

        $urutkan = app(UrutkanSlide::class);
        foreach ([[$a->id, $b->id], [$a->id, $b->id, $c->id, 999], [$a->id, $a->id, $b->id]] as $ids) {
            try {
                $urutkan->handle($ids);
                $this->fail('Urutan tidak valid diterima: '.implode(',', $ids));
            } catch (ValidationException) {
            }
        }
        $this->assertSame([$c->id, $a->id, $b->id], LandingSlide::orderBy('urutan')->pluck('id')->all());
    }

    public function test_naikkan_turunkan_and_disabled_ends(): void
    {
        [$a, $b, $c] = LandingSlide::factory()->count(3)->sequence(
            ['urutan' => 1, 'judul' => 'Alfa'], ['urutan' => 2, 'judul' => 'Beta'], ['urutan' => 3, 'judul' => 'Gama'])->create();

        $page = $this->page();
        $html = $page->html();
        // First card: Naikkan disabled; last card: Turunkan disabled.
        // The `disabled` attribute itself, not the `disabled:` Tailwind variants in the class list.
        $disabled = fn ($label) => '/<button[^>]*aria-label="'.$label.'"[^>]*\sdisabled[\s>=]/';
        $this->assertMatchesRegularExpression($disabled('Naikkan slide Alfa'), $html);
        $this->assertMatchesRegularExpression($disabled('Turunkan slide Gama'), $html);
        $this->assertDoesNotMatchRegularExpression($disabled('Turunkan slide Alfa'), $html);
        $this->assertDoesNotMatchRegularExpression($disabled('Naikkan slide Beta'), $html);

        $page->call('naikkan', $b->id);
        $this->assertSame([$b->id, $a->id, $c->id], LandingSlide::orderBy('urutan')->pluck('id')->all());
        $page->call('turunkan', $b->id);
        $this->assertSame([$a->id, $b->id, $c->id], LandingSlide::orderBy('urutan')->pluck('id')->all());
        // At the ends nothing moves.
        $page->call('naikkan', $a->id)->call('turunkan', $c->id);
        $this->assertSame([$a->id, $b->id, $c->id], LandingSlide::orderBy('urutan')->pluck('id')->all());
    }

    public function test_atur_aktif(): void
    {
        $s = LandingSlide::factory()->create(['aktif' => true]);
        $this->page()->call('aturAktif', $s->id, false);
        $this->assertFalse($s->fresh()->aktif);
        $this->page()->call('aturAktif', $s->id, true);
        $this->assertTrue($s->fresh()->aktif);
    }

    public function test_empty_state_and_list(): void
    {
        $this->page()->assertSee('Belum ada slide. Beranda menampilkan latar bawaan.');
        LandingSlide::factory()->create(['judul' => 'Slide Satu']);
        $this->page()->assertSee('Slide Satu')->assertDontSee('Belum ada slide.');
    }

    public function test_demo_seeder_creates_three_slides_with_files_and_kontak(): void
    {
        $this->seed(LandingSeeder::class);
        $this->seed(LandingSeeder::class); // idempotent
        $this->assertSame([1, 2, 3], LandingSlide::orderBy('urutan')->pluck('urutan')->all());
        foreach (LandingSlide::all() as $s) {
            $this->assertSame([1920, 1080, 'image/webp'], $this->ukuran($s->gambar.'-1920.webp'));
            $this->assertTrue($s->dekoratif || filled($s->alt));
        }
        $this->assertSame('inspektorat.muaraenim', app(Settings::class)->get('kontak.instagram'));
    }

    public function test_admin_dashboard_shows_slide_aktif(): void
    {
        LandingSlide::factory()->count(2)->create(['aktif' => true]);
        LandingSlide::factory()->create(['aktif' => false]);
        // 2 active of 3 slides.
        Livewire::actingAs(User::factory()->admin()->create())->test(Dasbor::class)
            ->assertSeeInOrder(['Slide aktif', '2', 'dari 3 slide']);
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertSee(route('admin.slide'), false);
    }
}
