<?php

namespace Tests\Feature\Dasbor;

use App\Enums\Role;
use App\Models\Irban;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Notifikasi page breadcrumb "Dasbor" links to the viewer's own dashboard (Fase 8, Task 6). */
class BreadcrumbNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dasbor_breadcrumb_links_to_the_role_dashboard(): void
    {
        $this->withoutVite();
        foreach (Role::cases() as $role) {
            $user = User::factory()->create(['role' => $role, 'irban_id' => $role === Role::Irban ? Irban::factory()->create()->id : null]);
            $html = $this->actingAs($user)->get('/notifikasi')->assertOk()->getContent();
            $this->assertMatchesRegularExpression(
                '#<nav aria-label="Breadcrumb".*?<a href="'.preg_quote(route($role->dashboardRoute()), '#').'"[^>]*>Dasbor</a>#s', $html, $role->value);
        }
    }
}
