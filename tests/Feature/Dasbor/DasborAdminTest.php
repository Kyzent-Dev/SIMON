<?php

namespace Tests\Feature\Dasbor;

use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Livewire\Admin\Dasbor;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Dasbor Admin (Fase 8, Task 6; admin.md "Dashboard"): health cards + quick links. Today 15 Juni 2026. */
class DasborAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->admin()->create();
    }

    public function test_route_is_the_livewire_page_and_other_roles_are_forbidden(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk()->assertSeeLivewire(Dasbor::class)->assertSee('Dasbor Admin');
        foreach ([Role::Pimpinan, Role::Irban, Role::BagianUmum] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/admin')->assertForbidden();
            Livewire::actingAs($u)->test(Dasbor::class)->assertForbidden();
        }
    }

    public function test_health_cards(): void
    {
        $this->assertSame(1, User::count());
        User::factory()->count(2)->create();                    // 3 users
        Pegawai::factory()->count(2)->create();
        Pegawai::factory()->create(['aktif' => false]);         // 2 active pegawai

        $h = Livewire::actingAs($this->admin)->test(Dasbor::class);
        $this->assertSame(3, $h->viewData('pengguna'));
        $this->assertSame(2, $h->viewData('pegawai'));
        $this->assertNull($h->viewData('pkpt'));
        $h->assertSee('PKPT 2026')->assertSee('Belum dibuat')->assertSee(route('admin.pkpt'), false)
            ->assertSee('Slide aktif')->assertSee('dari 0 slide')
            ->assertSee('Perubahan menunggu persetujuan')->assertSee('Tidak ada yang menunggu')->assertSee('SK belum dilengkapi')->assertDontSee('Impor terakhir')->assertSee('Aktivitas terbaru');

        $pkpt = Pkpt::factory()->create(['tahun' => 2026]);
        Livewire::actingAs($this->admin)->test(Dasbor::class)->assertSee('Draf')->assertDontSee('Belum dibuat');
        $pkpt->forceFill(['status' => StatusPkpt::Disahkan])->save();
        Livewire::actingAs($this->admin)->test(Dasbor::class)->assertSee('Disahkan');
    }
}
