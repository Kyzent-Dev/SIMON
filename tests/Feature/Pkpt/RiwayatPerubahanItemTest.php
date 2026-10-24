<?php

namespace Tests\Feature\Pkpt;

use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\User;
use App\Services\PerubahanPkpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Fase 9.2 Task 9: riwayat per item (Irban and Admin) with SK number/date and "Unduh SK" only for users the unduhSk gate allows. */
class RiwayatPerubahanItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $irban;

    private User $irbanLain;

    private PkptItem $item;

    private PkptPerubahan $denganSk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo('2026-03-25 10:00:00');
        $this->admin = User::factory()->admin()->create();
        $i1 = Irban::factory()->create(['nama' => 'Irban I']);
        $i2 = Irban::factory()->create(['nama' => 'Irban II']);
        $this->irban = User::factory()->irban()->create(['irban_id' => $i1->id]);
        $this->irbanLain = User::factory()->irban()->create(['irban_id' => $i2->id]);
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $jenis = JenisPenugasan::factory()->create(['nama' => 'Reviu']);
        $this->item = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $i1->id, 'jenis_penugasan_id' => $jenis->id,
            'tanggal_mulai' => '2026-04-13', 'tanggal_selesai' => '2026-04-24', 'tanggal_mulai_disahkan' => '2026-03-02', 'tanggal_selesai_disahkan' => '2026-03-13']);
        $data = fn (string $m, string $s) => ['irban_id' => $i1->id, 'jenis_penugasan_id' => $jenis->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s, 'keterangan' => null, 'target_spt' => null];

        $tanpaSk = PkptPerubahan::factory()->disetujui(1)->create(['pkpt_id' => $pkpt->id, 'diputuskan_pada' => '2026-03-05 09:00:00']);
        $tanpaSk->operasi()->create(['aksi' => 'ubah', 'pkpt_item_id' => $this->item->id, 'urutan' => 1,
            'data_lama' => $data('2026-03-02', '2026-03-13'), 'data_baru' => $data('2026-04-06', '2026-04-17')]);
        $this->denganSk = PkptPerubahan::factory()->disetujui(2)->create(['pkpt_id' => $pkpt->id, 'diputuskan_pada' => '2026-03-20 09:00:00',
            'nomor_sk' => '700/77/SK/2026', 'tanggal_sk' => '2026-03-18', 'berkas_sk' => 'perubahan-sk/a.pdf', 'hash_sk' => str_repeat('a', 64)]);
        $this->denganSk->operasi()->create(['aksi' => 'ubah', 'pkpt_item_id' => $this->item->id, 'urutan' => 1,
            'data_lama' => $data('2026-04-06', '2026-04-17'), 'data_baru' => $data('2026-04-13', '2026-04-24')]);
    }

    public function test_entries_in_order_with_sk_and_download_permission(): void
    {
        $r = app(PerubahanPkpt::class)->riwayatItemUntuk($this->irban, [$this->item->id])[$this->item->id];
        $this->assertSame([1, 2], array_column($r, 'nomor_urut'));
        $this->assertSame([false, true], array_column($r, 'boleh_unduh'));
        $this->assertSame('700/77/SK/2026', $r[1]['nomor_sk']);

        $lain = app(PerubahanPkpt::class)->riwayatItemUntuk($this->irbanLain, [$this->item->id])[$this->item->id];
        $this->assertSame([false, false], array_column($lain, 'boleh_unduh'));
    }

    public function test_irban_table_and_calendar_show_riwayat_with_sk_link(): void
    {
        $link = route('perubahan-pkpt.sk', ['perubahan' => $this->denganSk->id, 'unduh' => 1]);
        foreach (['tabel', 'kalender'] as $tampilan) {
            $this->actingAs($this->irban)->get('/irban/pkpt?tampilan='.$tampilan)->assertOk()
                ->assertSee('Riwayat perubahan (2)')->assertSee('Perubahan ke-1 · 05 Maret 2026')->assertSee('Perubahan ke-2 · 20 Maret 2026')
                ->assertSee('2–13 Maret 2026 → 6–17 April 2026')->assertSee('700/77/SK/2026')->assertSee('tanggal 18 Maret 2026')
                ->assertSee('6–17 April 2026 → 13–24 April 2026')->assertSee(e($link), false);
        }
        $this->actingAs($this->irbanLain)->get('/irban/pkpt?tampilan=tabel')->assertOk()->assertDontSee('700/77/SK/2026')->assertDontSee(e($link), false);
    }

    public function test_admin_item_tab_shows_riwayat_with_sk_link(): void
    {
        $this->actingAs($this->admin)->get(route('admin.pkpt.show', $this->item->pkpt_id))->assertOk()
            ->assertSee('Riwayat perubahan (2)')->assertSee('700/77/SK/2026')
            ->assertSee(e(route('perubahan-pkpt.sk', ['perubahan' => $this->denganSk->id, 'unduh' => 1])), false);
    }
}
