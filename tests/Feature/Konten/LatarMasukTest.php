<?php

namespace Tests\Feature\Konten;

use App\Livewire\Admin\LatarMasuk as Page;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LatarMasukTest extends TestCase
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

    /** Livewire::actingAs leaves the admin signed in, and /masuk redirects a signed-in user. */
    private function tamu(): static
    {
        auth()->forgetGuards();
        $this->app['auth']->guard()->logout();

        return $this;
    }

    private function foto(string $nama = 'a.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 2400, 1600);
    }

    private function base(): string
    {
        return (string) app(Settings::class)->get('masuk.latar');
    }

    public function test_only_admin_can_open_page(): void
    {
        $this->get('/admin/latar-masuk')->assertRedirect('/masuk');
        foreach (['pimpinan', 'irban', 'bagianUmum'] as $role) {
            $this->actingAs(User::factory()->$role()->create())->get('/admin/latar-masuk')->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create())->get('/admin/latar-masuk')->assertOk()->assertSee('Latar Halaman Masuk');
    }

    public function test_upload_renders_photo_and_scrim_on_login(): void
    {
        $this->get('/masuk')->assertOk()->assertDontSee('bg-mint-950/80', false)->assertDontSee('<img src="/storage/login', false);

        $this->page()->set('foto_latar', $this->foto())->call('save')->assertHasNoErrors()->assertDispatched('toast', message: 'Foto latar disimpan.');
        $base = $this->base();
        $this->assertMatchesRegularExpression('#^login/[0-9a-f-]{36}$#', $base);
        Storage::disk('public')->assertExists(["{$base}-960.webp", "{$base}-1920.webp"]);

        $this->tamu()->get('/masuk')->assertOk()
            ->assertSee("{$base}-960.webp 960w", false)->assertSee("{$base}-1920.webp 1920w", false)
            ->assertSee('alt="" class="absolute inset-0 size-full object-cover"', false)
            ->assertSee('bg-mint-950/80', false);
    }

    public function test_hapus_returns_to_plain_panel_and_deletes_files(): void
    {
        $c = $this->page()->set('foto_latar', $this->foto())->call('save');
        $base = $this->base();

        $c->call('confirmHapus')->assertSet('hapusOpen', true)->call('hapus')->assertSet('hapusOpen', false)->assertDispatched('toast');
        $this->assertSame('', $this->base());
        Storage::disk('public')->assertMissing(["{$base}-960.webp", "{$base}-1920.webp"]);
        $this->tamu()->get('/masuk')->assertOk()->assertDontSee('bg-mint-950/80', false)->assertDontSee($base, false);
    }

    public function test_replacing_deletes_the_old_files_only(): void
    {
        $c = $this->page()->set('foto_latar', $this->foto())->call('save');
        $lama = $this->base();
        $c->set('foto_latar', $this->foto('b.jpg'))->call('save')->assertHasNoErrors();
        $baru = $this->base();

        $this->assertNotSame($lama, $baru);
        Storage::disk('public')->assertMissing(["{$lama}-960.webp", "{$lama}-1920.webp"]);
        Storage::disk('public')->assertExists(["{$baru}-960.webp", "{$baru}-1920.webp"]);
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_bad_files_are_field_errors_and_change_nothing(): void
    {
        $this->page()->call('save')->assertHasErrors(['foto_latar'])->assertSee('Foto wajib dipilih.');
        $this->page()->set('foto_latar', UploadedFile::fake()->image('a.jpg', 959, 600))->assertHasErrors(['foto_latar'])->assertSee('Lebar gambar minimal 960 piksel.');
        $this->page()->set('foto_latar', UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'))->assertHasErrors(['foto_latar'])->assertSee('Foto harus JPG, PNG, atau WebP.');
        // Passes the header checks, then GD cannot decode it.
        $rusak = UploadedFile::fake()->createWithContent('rusak.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00\x13garbage", 400));
        $this->page()->set('foto_latar', $rusak)->call('save')->assertHasErrors(['foto_latar']);

        $this->assertSame('', $this->base());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_changes_are_logged(): void
    {
        $this->page()->set('foto_latar', $this->foto())->call('save');
        $log = Activity::where('log_name', 'pengaturan')->latest('id')->firstOrFail();
        $this->assertSame($this->base(), $log->properties['attributes']['masuk.latar']);
        $this->assertSame('', $log->properties['old']['masuk.latar']);
    }
}
