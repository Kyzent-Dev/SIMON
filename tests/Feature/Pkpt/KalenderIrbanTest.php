<?php

namespace Tests\Feature\Pkpt;

use App\Livewire\Irban\Pkpt as IrbanPage;
use App\Models\Irban;
use App\Models\IrbanWilayah;
use App\Models\JenisPenugasan;
use App\Models\Objek;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;
use Database\Seeders\IrbanSeeder;
use Database\Seeders\JenisPenugasanSeeder;
use Database\Seeders\PkptIrbanIDemo2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Spec Tugas B.5 #4–#5: Kalender view of the Irban "Jadwal PKPT" page. */
class KalenderIrbanTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private Irban $lain;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-10-21 10:00:00'); // Wed → Okt M3
        $this->seed([IrbanSeeder::class, JenisPenugasanSeeder::class, PkptIrbanIDemo2026Seeder::class]);
        $this->irban = Irban::where('nama', 'Irban I')->first();
        $this->lain = Irban::where('id', '!=', $this->irban->id)->first();
        $this->user = User::factory()->irban()->create();
        $this->user->forceFill(['irban_id' => $this->irban->id])->save();
    }

    private function item(string $mulai): PkptItem
    {
        return PkptItem::where('irban_id', $this->irban->id)->whereDate('tanggal_mulai', $mulai)->firstOrFail();
    }

    public function test_default_is_calendar_and_tabel_renders_the_table(): void
    {
        $this->actingAs($this->user)->get('/irban/pkpt')->assertOk()
            ->assertSee('data-kalender-pkpt', false)->assertSee('Minggu ini')->assertSee('6–10 Apr')
            ->assertSee('1 tim 1 minggu 2 sekolah')->assertDontSee('Revisi terakhir');

        $this->actingAs($this->user)->get('/irban/pkpt?tampilan=tabel')->assertOk()
            ->assertSee('Revisi terakhir')->assertDontSee('data-kalender-pkpt', false);

        $this->actingAs($this->user)->get('/irban/pkpt?tampilan=xyz')->assertOk()
            ->assertSee('data-kalender-pkpt', false)->assertDontSee('Revisi terakhir');

        Livewire::actingAs($this->user)->test(IrbanPage::class)
            ->assertSet('tampilan', 'kalender')
            ->set('tampilan', 'tabel')->assertSee('Revisi terakhir')
            ->set('tampilan', 'bogus')->assertSet('tampilan', 'kalender')->assertSee('data-kalender-pkpt', false);
    }

    public function test_only_own_items_and_wilayah(): void
    {
        $jenis = JenisPenugasan::create(['nama' => 'Jenis Rahasia Irban Lain', 'warna' => '#332288', 'warna_teks' => '#FFFFFF']);
        PkptItem::factory()->create(['pkpt_id' => Pkpt::where('tahun', 2026)->value('id'), 'irban_id' => $this->lain->id,
            'jenis_penugasan_id' => $jenis->id, 'tanggal_mulai' => '2026-05-11', 'tanggal_selesai' => '2026-05-15', 'keterangan' => 'Keterangan irban lain']);
        $sendiri = Objek::factory()->create(['nama' => 'Kecamatan Milik Sendiri', 'jenis' => 'kecamatan']);
        $bumd = Objek::factory()->create(['nama' => 'Perumdam Lematang Enim', 'jenis' => 'bumd']);
        $orang = Objek::factory()->create(['nama' => 'Kecamatan Milik Lain', 'jenis' => 'kecamatan']);
        IrbanWilayah::create(['irban_id' => $this->irban->id, 'tahun' => 2026, 'objek_id' => $sendiri->id]);
        IrbanWilayah::create(['irban_id' => $this->irban->id, 'tahun' => 2026, 'objek_id' => $bumd->id]);
        IrbanWilayah::create(['irban_id' => $this->lain->id, 'tahun' => 2026, 'objek_id' => $orang->id]);

        $this->actingAs($this->user)->get('/irban/pkpt')->assertOk()
            ->assertSee('Wilayah Pengawasan')->assertSee('Kecamatan Milik Sendiri')->assertSee('Perumdam Lematang Enim')
            ->assertSee('RSUD, Puskesmas, Desa, BUMDes, dan Sekolah mengikuti wilayah kecamatan.')
            ->assertDontSee('Kecamatan Milik Lain')->assertDontSee('Jenis Rahasia Irban Lain')->assertDontSee('Keterangan irban lain');
    }

    public function test_buka_from_calendar_opens_modal_and_scopes_to_own_items(): void
    {
        $item = $this->item('2026-10-19');
        $lain = PkptItem::factory()->create(['pkpt_id' => $item->pkpt_id, 'irban_id' => $this->lain->id]);

        Livewire::actingAs($this->user)->test(IrbanPage::class)
            ->assertSee('wire:click="buka('.$item->id.')"', false)
            ->call('buka', $item->id)->assertSet('open', true)->assertSet('itemId', $item->id)
            ->assertSet('tanggal_mulai', '2026-10-19')->assertSee('Alasan revisi');

        Livewire::actingAs($this->user)->test(IrbanPage::class)->call('buka', $lain->id)->assertNotFound();
    }

    public function test_pending_revision_disables_with_reason_and_buka_toasts(): void
    {
        $item = $this->item('2026-10-05');
        PkptItemRevisi::factory()->create(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2026-12-02']);

        Livewire::actingAs($this->user)->test(IrbanPage::class)
            ->assertSee('Masih ada revisi yang menunggu persetujuan.')
            ->assertDontSee('wire:click="buka('.$item->id.')"', false)
            ->call('buka', $item->id)->assertSet('open', false)
            ->assertDispatched('toast', type: 'error', message: 'Item ini masih punya revisi yang menunggu persetujuan.');
    }

    public function test_approved_revision_shows_marker_and_semula(): void
    {
        $item = $this->item('2026-06-29');
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-11-02', 'tanggal_selesai' => '2026-11-06']);

        $this->actingAs($this->user)->get('/irban/pkpt')->assertOk()
            ->assertSee('Jadwal direvisi')->assertSee('29 Juni – 3 Juli 2026')->assertSee('2–6 November 2026');
    }

    public function test_legend_lists_present_jenis_only(): void
    {
        $absen = JenisPenugasan::create(['nama' => 'Jenis Tanpa Item', 'warna' => '#332288', 'warna_teks' => '#FFFFFF']);

        $html = $this->actingAs($this->user)->get('/irban/pkpt')->assertOk()->getContent();
        preg_match('/aria-label="Legenda jenis penugasan".*?<\/ul>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString('Monev', $m[0]);
        $this->assertStringContainsString('Audit Ketaatan OPD', $m[0]);
        $this->assertStringNotContainsString($absen->nama, $m[0]);
        $this->assertSame(PkptItem::where('irban_id', $this->irban->id)->distinct()->count('jenis_penugasan_id'), substr_count($m[0], '<li'));
    }
}
