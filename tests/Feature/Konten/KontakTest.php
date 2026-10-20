<?php

namespace Tests\Feature\Konten;

use App\Enums\Role;
use App\Livewire\Admin\Kontak as Page;
use App\Livewire\Admin\Pengaturan;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KontakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function page()
    {
        return Livewire::actingAs(User::factory()->admin()->create())->test(Page::class);
    }

    public function test_only_admin_can_open_page_and_save(): void
    {
        $this->get('/admin/kontak')->assertRedirect('/masuk');
        foreach (['pimpinan', 'irban', 'bagianUmum'] as $role) {
            $this->actingAs(User::factory()->$role()->create())->get('/admin/kontak')->assertForbidden();
        }
        $this->actingAs(User::factory()->admin()->create())->get('/admin/kontak')->assertOk()->assertSee('Kontak &amp; Footer', false);
        Livewire::actingAs(User::factory()->irban()->create())->test(Page::class)->assertForbidden();

        $admin = User::factory()->admin()->create();
        $c = Livewire::actingAs($admin)->test(Page::class)->set('v.kontak.telepon', '0734 421');
        $admin->role = Role::Irban;
        $c->call('save')->assertForbidden();
        $this->assertSame('', app(Settings::class)->get('kontak.telepon'));
    }

    public function test_save_valid_values_and_strip_at_sign(): void
    {
        $this->page()
            ->set('v.kontak.telepon', ' (0734) 421-001 ')
            ->set('v.kontak.email', 'inspektorat@muaraenimkab.go.id')
            ->set('v.kontak.instagram', '@inspektorat.me')
            ->set('v.kontak.alamat', 'Jl. Jenderal Sudirman No. 1, Muara Enim')
            ->set('v.kontak.peta_url', 'https://www.google.com/maps/embed?pb=!1m18')
            ->call('save')->assertHasNoErrors()->assertDispatched('toast', message: 'Kontak disimpan.')
            ->assertSet('v.kontak.instagram', 'inspektorat.me');

        $s = app(Settings::class);
        $this->assertSame('(0734) 421-001', $s->get('kontak.telepon'));
        $this->assertSame('inspektorat.me', $s->get('kontak.instagram'));
        $this->assertSame('https://www.google.com/maps/embed?pb=!1m18', $s->get('kontak.peta_url'));
    }

    public function test_empty_values_are_allowed(): void
    {
        $this->page()->call('save')->assertHasNoErrors();
    }

    public function test_invalid_values_are_rejected(): void
    {
        $this->page()->set('v.kontak.telepon', '0734-abc')->call('save')->assertHasErrors(['v.kontak.telepon']);
        $this->page()->set('v.kontak.email', 'bukan-email')->call('save')->assertHasErrors(['v.kontak.email']);
        $this->page()->set('v.kontak.instagram', 'ada spasi')->call('save')->assertHasErrors(['v.kontak.instagram']);
        $this->page()->set('v.kontak.alamat', str_repeat('a', 301))->call('save')->assertHasErrors(['v.kontak.alamat']);
        foreach (['https://maps.google.com/?q=muara+enim', 'https://evil.example/https://www.google.com/maps/embed?', 'http://www.google.com/maps/embed?pb=1'] as $url) {
            $this->page()->set('v.kontak.peta_url', $url)->call('save')->assertHasErrors(['v.kontak.peta_url'])
                ->assertSee('Gunakan tautan sematan Google Maps (https://www.google.com/maps/embed?...).');
        }
        $this->assertSame('', app(Settings::class)->get('kontak.peta_url'));
    }

    public function test_preview_uses_footer_partial(): void
    {
        $s = app(Settings::class);
        $s->set('kontak.telepon', '0734 421001');
        $s->set('kontak.instagram', 'inspektorat.me');
        $this->page()->assertSee('Hubungi Kami')->assertSee('tel:0734421001', false)
            ->assertSee('https://www.instagram.com/inspektorat.me', false)->assertSee('@inspektorat.me')
            ->assertDontSee('Alamat Kantor') // empty column is omitted
            ->assertSee('Inspektorat Daerah Kabupaten Muara Enim');
    }

    public function test_pengaturan_page_does_not_render_kontak_card(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())->test(Pengaturan::class)
            ->assertDontSee('v.kontak', false)->assertDontSee('Tautan sematan peta');
    }
}
