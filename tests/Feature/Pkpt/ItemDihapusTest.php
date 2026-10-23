<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Spt\SimpanSpt;
use App\Livewire\Irban\Pkpt as IrbanPkpt;
use App\Livewire\Irban\SptForm;
use App\Models\JenisPenugasan;
use App\Models\PkptItem;
use App\Models\User;
use App\Services\KalenderPkpt;
use App\Services\Publik\JadwalPublik;
use App\Services\Publik\RealisasiPublik;
use App\Services\RealisasiItemPkpt;
use App\Services\RekapTriwulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Publik\PublikFixture;
use Tests\TestCase;

/**
 * Fase 9.2 Task 5 (spec §4.4): an item removed by a Perubahan PKPT (status dihapus) is excluded from every view and count.
 * On top of PublikFixture: Irban A gets a dihapus item "Jenis Terhapus", 4 Mei–29 Mei 2026, target 5.
 */
class ItemDihapusTest extends TestCase
{
    use PublikFixture { setUp as fixtureSetUp; }
    use RefreshDatabase;

    private PkptItem $hapus;

    private User $irbanA;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->withoutVite();
        $jenis = JenisPenugasan::factory()->create(['nama' => 'Jenis Terhapus']);
        $this->hapus = PkptItem::factory()->dihapus()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->a->id, 'jenis_penugasan_id' => $jenis->id,
            'tanggal_mulai' => '2026-05-04', 'tanggal_selesai' => '2026-05-29', 'target_spt' => 5, 'keterangan' => 'ITEM-DIHAPUS']);
        $this->irbanA = User::factory()->irban()->create();
        $this->irbanA->forceFill(['irban_id' => $this->a->id])->save();
    }

    public function test_absent_from_rekap_rencana_and_detail_realisasi(): void
    {
        $rekap = app(RekapTriwulan::class)->hitung(2026)['irban']['baris'];
        $this->assertSame([2, 1, 0], array_column(array_column($rekap, 'total'), 'rencana')); // A = a1 only (target 2), not + 5

        $ids = collect(app(RealisasiItemPkpt::class)->untuk(2026, 0, null))->flatMap(fn ($g) => array_column($g['item'], 'item_id'))->all();
        $this->assertContains($this->a1->id, $ids);
        $this->assertNotContains($this->hapus->id, $ids);
    }

    public function test_absent_from_kalender_jadwal_publik_and_realisasi_publik(): void
    {
        $kalender = collect(app(KalenderPkpt::class)->untuk(2026, $this->a->id))
            ->flatMap(fn ($b) => collect($b['minggu'])->flatMap(fn ($m) => array_column($m['item'], 'id')))->unique()->values()->all();
        $this->assertSame([$this->a1->id], $kalender);

        $this->assertSame([$this->b1->id, $this->a1->id], array_column(app(JadwalPublik::class)->item(2026, null), 'id'));
        $this->assertNotContains($this->hapus->id, app(RealisasiPublik::class)->itemDisahkan(2026)->modelKeys());
        $this->assertSame(3, app(RealisasiPublik::class)->ringkasan(['tahun' => 2026])['rencana']); // a1 2 + b1 1

        $this->get('/jadwal')->assertOk()->assertDontSee('aria-label="Jenis Terhapus', false);
    }

    public function test_absent_from_irban_pages_and_rejected_by_simpan_spt(): void
    {
        $pkpt = Livewire::actingAs($this->irbanA)->test(IrbanPkpt::class, ['tahun' => '2026']);
        $this->assertSame([$this->a1->id], $pkpt->viewData('items')->modelKeys());
        $pkpt->set('tampilan', 'tabel')->assertDontSee('ITEM-DIHAPUS');

        $form = Livewire::actingAs($this->irbanA)->test(SptForm::class);
        $this->assertArrayHasKey($this->a1->id, $form->viewData('itemOptions'));
        $this->assertArrayNotHasKey($this->hapus->id, $form->viewData('itemOptions'));
        $this->assertSame([], app(SimpanSpt::class)->peringatan(['pkpt_item_id' => $this->hapus->id, 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-01-02'], $this->a->id));

        try {
            app(SimpanSpt::class)->handle($this->irbanA, null, ['klasifikasi_id' => $this->reguler, 'pkpt_item_id' => $this->hapus->id], 'draf');
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertSame('Item PKPT tidak valid untuk Irban ini.', $e->errors()['pkpt_item_id'][0]);
        }
    }
}
