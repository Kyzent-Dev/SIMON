<?php

namespace Tests\Feature\Pkpt;

use App\Enums\StatusPkpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\Spt;
use Database\Seeders\IrbanSeeder;
use Database\Seeders\JenisPenugasanSeeder;
use Database\Seeders\PkptIrbanIDemo2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PkptIrbanIDemo2026SeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedDemo(): void
    {
        $this->seed([IrbanSeeder::class, JenisPenugasanSeeder::class, PkptIrbanIDemo2026Seeder::class]);
    }

    private function irbanItems()
    {
        return PkptItem::where('irban_id', Irban::where('nama', 'Irban I')->value('id'))
            ->whereHas('pkpt', fn ($q) => $q->where('tahun', 2026));
    }

    public function test_seeds_25_items_with_exact_values(): void
    {
        $this->seedDemo();

        $items = $this->irbanItems()->with('jenisPenugasan')->orderBy('tanggal_mulai')->get();
        $this->assertCount(25, $items);
        $this->assertSame(StatusPkpt::Disahkan, Pkpt::where('tahun', 2026)->first()->status);
        $this->assertSame(['2026-01-12', '2026-01-23', 'Evaluasi Risk Register', null], [
            $items[0]->tanggal_mulai->toDateString(), $items[0]->tanggal_selesai->toDateString(), $items[0]->jenisPenugasan->nama, $items[0]->keterangan,
        ]);
        $this->assertSame('Pengawasan Dana BOS', $items[2]->jenisPenugasan->nama);
        $this->assertSame('1 tim 1 minggu 2 sekolah', $items[2]->keterangan);
        $this->assertSame(['2026-12-07', '2026-12-25', 'Monev'], [
            $items[24]->tanggal_mulai->toDateString(), $items[24]->tanggal_selesai->toDateString(), $items[24]->jenisPenugasan->nama,
        ]);
        $this->assertNull($items[24]->target_spt);
        $this->assertSame(1, JenisPenugasan::where('nama', 'Evaluasi RB dan Reviu DAK')->count());
    }

    public function test_seeder_is_a_noop_outside_local_and_testing(): void
    {
        $this->app['env'] = 'staging';
        $this->seedDemo();

        $this->assertSame(0, PkptItem::count());
    }

    public function test_second_run_keeps_25_and_removes_stray_items_without_spt(): void
    {
        $this->seedDemo();
        $irbanId = Irban::where('nama', 'Irban I')->value('id');
        $irbanII = Irban::where('nama', 'Irban II')->value('id');
        $pkpt = Pkpt::where('tahun', 2026)->first();
        $jenis = JenisPenugasan::first();
        $stray = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $irbanId, 'jenis_penugasan_id' => $jenis->id]);
        $withSpt = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $irbanId, 'jenis_penugasan_id' => $jenis->id]);
        Spt::factory()->create(['pkpt_item_id' => $withSpt->id, 'irban_id' => $irbanId]);
        $other = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $irbanII, 'jenis_penugasan_id' => $jenis->id]);

        $this->seed(PkptIrbanIDemo2026Seeder::class);

        $this->assertCount(26, $this->irbanItems()->get()); // 25 + the one that has an SPT
        $this->assertModelMissing($stray);
        $this->assertModelExists($withSpt);
        $this->assertModelExists($other);
    }

    public function test_existing_non_disahkan_pkpt_keeps_its_status(): void
    {
        Pkpt::factory()->create(['tahun' => 2026, 'status' => StatusPkpt::Draf]);
        $this->seedDemo();

        $this->assertSame(StatusPkpt::Draf, Pkpt::where('tahun', 2026)->first()->status);
        $this->assertCount(25, $this->irbanItems()->get());
    }
}
