<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DashboardShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function fakeMasterDataRoutes(): void
    {
        Route::middleware(['web', 'auth', 'role:admin'])->group(function () {
            Route::get('/admin/slide', fn () => 'ok')->name('admin.slide');
            Route::get('/admin/pengaturan', fn () => 'ok')->name('admin.pengaturan');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    public function test_admin_sidebar_marks_active_item_and_shows_existing_master_data_routes(): void
    {
        $this->fakeMasterDataRoutes();
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('aria-label="Navigasi utama"', false)
            ->assertSee('Master Data')
            ->assertSee('href="'.route('admin.slide').'"', false)
            ->assertSee('Pengaturan Sistem')
            ->assertSee('Ciutkan menu')
            ->assertSee('aria-label="Buka menu"', false)
            ->getContent();

        // aria-current only on the Dasbor link.
        $this->assertSame(1, substr_count($html, 'aria-current="page"'));
        $this->assertMatchesRegularExpression('#<a[^>]+href="'.preg_quote(route('admin.dashboard'), '#').'"[^>]*aria-current="page"#', $html);
        // Items without a route never render, and neither does their group label.
        $this->assertStringNotContainsString('Kontak & Footer', $html);
        $this->assertStringNotContainsString('Log Aktivitas', $html);
    }

    public function test_empty_groups_are_dropped(): void
    {
        $groups = Navigation::groups(Role::Admin);
        $labels = array_column($groups, 'label');
        // Master Data survives because admin.klasifikasi exists; its missing siblings (e.g. admin.slide) are filtered out.
        $this->assertContains('Master Data', $labels);
        $routes = array_column(array_merge(...array_column($groups, 'items')), 'route');
        $this->assertContains('admin.klasifikasi', $routes);
        $this->assertNotContains('admin.slide', $routes);
        // Konten Beranda has no existing route yet, so the whole group (label included) is dropped.
        $this->assertFalse(Route::has('admin.slide'));
        $this->assertNotContains('Konten Beranda', $labels);
        foreach ($groups as $group) {
            $this->assertNotEmpty($group['items']);
            foreach ($group['items'] as $item) {
                $this->assertTrue(Route::has($item['route']));
            }
        }
    }

    public function test_other_roles_see_only_dasbor(): void
    {
        $this->fakeMasterDataRoutes();
        $paths = ['pimpinan' => '/pimpinan', 'irban' => '/irban', 'bagianUmum' => '/bagian-umum'];

        foreach ($paths as $state => $path) {
            $user = User::factory()->{$state}()->create();
            $this->actingAs($user)->get($path)->assertOk()
                ->assertSee('Dasbor')
                ->assertDontSee('Master Data')
                ->assertDontSee(route('admin.slide'));
        }
        foreach ([Role::Pimpinan, Role::Irban, Role::BagianUmum] as $role) {
            $this->assertSame(['Dasbor'], array_column(Navigation::groups($role)[0]['items'], 'label'));
            $this->assertCount(2, Navigation::groups($role));
        }
        $this->assertSame(['Kotak Masuk Pengajuan'], array_column(Navigation::groups(Role::BagianUmum)[1]['items'], 'label'));
        $this->assertSame(['SPT Saya', 'Ajukan Nomor SPT', 'Jadwal PKPT'], array_column(Navigation::groups(Role::Irban)[1]['items'], 'label'));
        $this->assertSame(['Revisi Jadwal'], array_column(Navigation::groups(Role::Pimpinan)[1]['items'], 'label'));
    }

    public function test_admin_dashboard_quick_links_come_from_navigation(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Akses cepat')->assertDontSee(url('/admin/slide'));

        $this->fakeMasterDataRoutes();
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('<h1', false)
            ->assertSee('Akses cepat')
            ->assertSeeInOrder(['Akses cepat', url('/admin/slide'), 'Pengaturan Sistem'], false);
    }

    public function test_breadcrumbs_render_when_passed(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user);
        $html = (string) $this->blade(
            '<x-layouts::app title="X" :breadcrumbs="[\'Dasbor\' => \'/admin\', \'Master Data\' => null, \'Pegawai\' => null]">isi</x-layouts::app>'
        );
        $this->assertStringContainsString('aria-label="Breadcrumb"', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page"')); // only the last crumb (no nav item is active here)
        $this->assertMatchesRegularExpression('#<span[^>]*aria-current="page"[^>]*>Pegawai</span>#', $html);
    }

    public function test_layout_renders_toast_stack(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()
            ->assertSee('x-on:toast.window', false);
    }

    public function test_flagged_user_cannot_use_livewire_update_endpoint(): void
    {
        $user = User::factory()->admin()->create(['wajib_ganti_password' => true]);

        $this->actingAs($user)
            ->postJson(route('default-livewire.update'), ['components' => []], ['X-Livewire' => 'true'])
            ->assertRedirect('/perbarui-kata-sandi');
    }
}
