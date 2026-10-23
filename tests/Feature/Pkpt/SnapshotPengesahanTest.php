<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\SahkanPkpt;
use App\Enums\Role;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\User;
use App\Services\KesesuaianPkpt;
use App\Services\Publik\JadwalPublik;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 9.2 Task 5: SahkanPkpt snapshot ("jadwal semula"), the item's own dates as the only source, and the removed revisi page. */
class SnapshotPengesahanTest extends TestCase
{
    use RefreshDatabase;

    public function test_sahkan_fills_the_snapshot_and_moving_dates_makes_the_item_direvisi(): void
    {
        $pkpt = Pkpt::factory()->create(['tahun' => 2026, 'nomor_sk' => '100/1/2026', 'tanggal_sk' => '2026-01-02']);
        $item = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']);
        $this->assertNull($item->tanggal_mulai_disahkan);
        $this->assertFalse($item->direvisi());
        $this->assertNull($item->semula());

        app(SahkanPkpt::class)->handle($pkpt, User::factory()->admin()->create());

        $item->refresh();
        $this->assertSame(['2026-03-02', '2026-03-20'], [$item->tanggal_mulai_disahkan->toDateString(), $item->tanggal_selesai_disahkan->toDateString()]);
        $this->assertFalse($item->direvisi());
        $this->assertSame(['2026-03-02', '2026-03-20'], array_map(fn ($d) => $d->toDateString(), app(KesesuaianPkpt::class)->rentang($item)));

        $item->update(['tanggal_mulai' => '2026-04-06', 'tanggal_selesai' => '2026-04-24']);
        $this->assertTrue($item->direvisi());
        $this->assertSame(['2026-03-02', '2026-03-20'], array_map(fn ($d) => $d->toDateString(), $item->semula()));
        $this->assertSame(['2026-04-06', '2026-04-24'], array_map(fn ($d) => $d->toDateString(), app(KesesuaianPkpt::class)->rentang($item)));

        $publik = app(JadwalPublik::class)->item(2026, null)[0];
        $this->assertSame(['2026-04-06', '2026-03-02', '2026-03-20'], [$publik['mulai']->toDateString(), $publik['semula'][0]->toDateString(), $publik['semula'][1]->toDateString()]);
    }

    public function test_old_revisi_jadwal_route_redirects_and_nav_has_no_entry(): void
    {
        $pimpinan = User::factory()->pimpinan()->create();
        $this->actingAs($pimpinan)->get('/pimpinan/revisi-jadwal')->assertRedirect(route('pimpinan.dashboard'));
        $this->assertSame(url('/pimpinan/revisi-jadwal'), route('pimpinan.revisi-jadwal'));
        $this->actingAs(User::factory()->irban()->create())->get('/pimpinan/revisi-jadwal')->assertForbidden();

        $label = collect(Navigation::groups(Role::Pimpinan))->flatMap(fn ($g) => array_column($g['items'], 'label'))->all();
        $this->assertNotContains('Revisi Jadwal', $label);
    }
}
