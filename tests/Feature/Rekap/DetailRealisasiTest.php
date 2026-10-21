<?php

namespace Tests\Feature\Rekap;

use App\Enums\Role;
use App\Livewire\Rekap;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\User;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Tab "Detail Realisasi" on the Rekap page (spec A.2/A.4, A.6 #6 page part). Data rules are covered by RealisasiItemPkptTest. */
class DetailRealisasiTest extends TestCase
{
    use RefreshDatabase;

    private Irban $a;

    private Irban $b;

    private Irban $c;

    private Spt $spt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00', 'Asia/Jakarta'));
        app(Settings::class)->set('rekap.dasar_triwulan', 'tanggal_selesai');
        $this->a = Irban::factory()->create(['nama' => 'Irban A']);
        $this->b = Irban::factory()->create(['nama' => 'Irban B']);
        $this->c = Irban::factory()->create(['nama' => 'Irban C']);
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $item = fn (Irban $i, string $nama, string $m, string $s) => PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $i->id,
            'jenis_penugasan_id' => JenisPenugasan::factory()->create(['nama' => $nama])->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s]);
        $bos = $item($this->a, 'Pengawasan Dana BOS', '2026-02-02', '2026-02-27');
        $item($this->a, 'Reviu LKjIP', '2026-03-02', '2026-03-06');
        $item($this->b, 'Audit Ketaatan OPD', '2026-02-09', '2026-02-13');
        $this->spt = Spt::factory()->diterima()->create(['irban_id' => $this->a->id, 'pkpt_item_id' => $bos->id, 'jenis_penugasan_id' => $bos->jenis_penugasan_id,
            'tanggal_mulai' => '2026-02-02', 'tanggal_selesai' => '2026-02-06']);
    }

    private function detail(User $u, array $props = [])
    {
        $t = Livewire::actingAs($u)->test(Rekap::class);
        foreach (['tab' => 'detail', ...$props] as $k => $v) {
            $t->set($k, $v);
        }

        return $t;
    }

    private function irbanA(): User
    {
        return User::factory()->create(['role' => Role::Irban, 'irban_id' => $this->a->id]);
    }

    public function test_pimpinan_has_three_tabs_and_irban_two(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pimpinan]))->get('/pimpinan/rekap')->assertOk()
            ->assertSeeInOrder(['role="tab"', 'Per Irban', 'Gabungan', 'Detail Realisasi'], false);
        $html = $this->actingAs($this->irbanA())->get('/irban/rekap')->assertOk()
            ->assertSeeInOrder(['role="tablist"', 'Ringkasan', 'Detail Realisasi'], false)->getContent();
        $this->assertStringNotContainsString('Gabungan', $html);
        $this->assertSame(2, substr_count($html, 'id="rekap-panel-tab-'));
    }

    public function test_detail_tab_lists_items_per_irban_with_spt_rows_and_empty_rows(): void
    {
        $t = $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['tw' => '1']);

        $t->assertSet('tab', 'detail')->assertSee('Kesesuaian dengan Jadwal PKPT')->assertSee('Permasalahan/Kendala')
            ->assertDontSee('Rencana vs Realisasi')->assertDontSee('Per Klasifikasi');
        $html = $t->html();
        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Irban A\s*<\/h2>/', $html);
        $this->assertStringContainsString('href="'.route('spt.show', $this->spt).'"', $html);
        // Desktop table (rendered first): BOS row with its SPT, LKjIP without SPT, B's item, C's empty state.
        $pos = 0;
        foreach (['Irban A', 'Pengawasan Dana BOS', '2–27 Februari 2026', '2–6 Februari 2026', e($this->spt->nomor_lengkap), 'Reviu LKjIP', 'Belum ada SPT',
            'Irban B', 'Audit Ketaatan OPD', 'Belum ada SPT', 'Irban C', 'Tidak ada item PKPT pada periode ini.'] as $teks) {
            $pos = strpos($html, $teks, $pos);
            $this->assertNotFalse($pos, $teks);
        }
    }

    public function test_pimpinan_irban_param_scopes_to_one_irban(): void
    {
        $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['irban' => (string) $this->b->id])
            ->assertSee('Audit Ketaatan OPD')->assertDontSee('Irban A')->assertDontSee('Pengawasan Dana BOS')->assertDontSee('Irban C');
    }

    public function test_irban_sees_only_own_irban_even_with_tampered_param(): void
    {
        $this->detail($this->irbanA(), ['irban' => (string) $this->b->id])
            ->assertSee('Pengawasan Dana BOS')->assertSee('Rekap Saya')
            ->assertDontSee('Irban B')->assertDontSee('Audit Ketaatan OPD')->assertDontSee('Irban C');
    }

    public function test_empty_state_for_irban_without_items_in_the_period(): void
    {
        $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['irban' => (string) $this->a->id, 'tw' => '3'])
            ->assertSee('Irban A')->assertSee('Tidak ada item PKPT pada periode ini.')->assertDontSee('Pengawasan Dana BOS');
    }
}
