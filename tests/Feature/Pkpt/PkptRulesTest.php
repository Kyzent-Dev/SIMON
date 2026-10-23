<?php

namespace Tests\Feature\Pkpt;

use App\Actions\MasterData\DeleteIrban;
use App\Actions\MasterData\DeleteJenisPenugasan;
use App\Actions\Pkpt\DeletePkpt;
use App\Actions\Pkpt\DeletePkptItem;
use App\Actions\Pkpt\SahkanPkpt;
use App\Actions\Pkpt\SavePkpt;
use App\Actions\Pkpt\SavePkptItem;
use App\Enums\StatusPkpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\User;
use App\Services\KesesuaianPkpt;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PkptRulesTest extends TestCase
{
    use RefreshDatabase;

    private function assertRejected(callable $fn, string $key, string $message): void
    {
        try {
            $fn();
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()[$key][0] ?? null);
        }
    }

    private function itemData(array $over = []): array
    {
        return array_merge([
            'irban_id' => Irban::factory()->create()->id, 'jenis_penugasan_id' => JenisPenugasan::factory()->create()->id,
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20',
        ], $over);
    }

    public function test_one_pkpt_per_year_and_year_bounds(): void
    {
        $pkpt = (new SavePkpt)->handle(null, ['tahun' => 2026]);
        $this->assertSame(2026, $pkpt->tahun);
        $this->assertSame(StatusPkpt::Draf, $pkpt->fresh()->status);

        $this->assertRejected(fn () => (new SavePkpt)->handle(null, ['tahun' => 2026]), 'tahun', 'PKPT tahun 2026 sudah ada.');
        $this->assertRejected(fn () => (new SavePkpt)->handle(null, ['tahun' => 1999]), 'tahun', 'Tahun harus antara 2000 dan '.(now()->year + 1).'.');
        $this->assertRejected(fn () => (new SavePkpt)->handle(null, ['tahun' => now()->year + 2]), 'tahun', 'Tahun harus antara 2000 dan '.(now()->year + 1).'.');
        $this->assertSame(now()->year + 1, (new SavePkpt)->handle(null, ['tahun' => now()->year + 1])->tahun);
        $this->assertSame('X', (new SavePkpt)->handle($pkpt, ['tahun' => 2026, 'nomor_sk' => 'X'])->nomor_sk);
    }

    public function test_item_date_rules(): void
    {
        $pkpt = Pkpt::factory()->create(['tahun' => 2026]);
        $save = fn (array $o) => (new SavePkptItem)->handle($pkpt, null, $this->itemData($o));

        $this->assertRejected(fn () => $save(['tanggal_mulai' => '2026-03-20', 'tanggal_selesai' => '2026-03-19']), 'tanggal_selesai', 'Tanggal selesai tidak boleh sebelum tanggal mulai.');
        $this->assertRejected(fn () => $save(['tanggal_mulai' => '2025-12-31']), 'tanggal_mulai', 'Tanggal harus berada di tahun 2026.');
        $this->assertRejected(fn () => $save(['tanggal_selesai' => '2027-01-01']), 'tanggal_selesai', 'Tanggal harus berada di tahun 2026.');
        $this->assertRejected(fn () => $save(['target_spt' => 1000]), 'target_spt', 'Target SPT harus berupa angka 1 sampai 999.');
        $this->assertRejected(fn () => $save(['keterangan' => str_repeat('a', 256)]), 'keterangan', 'Keterangan maksimal 255 karakter.');
        $this->assertRejected(fn () => $save(['irban_id' => 999999]), 'irban_id', 'Pilih irban.');
        $this->assertRejected(fn () => $save(['jenis_penugasan_id' => null]), 'jenis_penugasan_id', 'Pilih jenis penugasan.');

        foreach ([null, '', '  '] as $blank) {
            $this->assertRejected(fn () => $save(['tanggal_mulai' => $blank]), 'tanggal_mulai', 'Tanggal mulai wajib diisi.');
            $this->assertRejected(fn () => $save(['tanggal_selesai' => $blank]), 'tanggal_selesai', 'Tanggal selesai wajib diisi.');
        }
        $this->assertRejected(fn () => (new SavePkptItem)->handle($pkpt, null, array_diff_key($this->itemData(), ['tanggal_mulai' => 1])), 'tanggal_mulai', 'Tanggal mulai wajib diisi.');

        $item = $save(['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31', 'target_spt' => '999']);
        $this->assertSame('2026-12-31', $item->fresh()->tanggal_selesai->toDateString());
        $this->assertSame(999, $item->fresh()->target_spt);
    }

    public function test_disahkan_pkpt_rejects_every_change(): void
    {
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $item = PkptItem::factory()->for($pkpt)->create();
        $msg = 'PKPT yang sudah disahkan tidak dapat diubah.';

        $this->assertRejected(fn () => (new SavePkpt)->handle($pkpt, ['tahun' => 2026, 'nomor_sk' => 'Y']), 'pkpt', $msg);
        $this->assertRejected(fn () => (new SavePkptItem)->handle($pkpt, null, $this->itemData()), 'pkpt', $msg);
        $this->assertRejected(fn () => (new SavePkptItem)->handle($pkpt, $item, $this->itemData()), 'pkpt', $msg);
        $this->assertRejected(fn () => (new DeletePkptItem)->handle($item), 'pkpt', $msg);
        $this->assertRejected(fn () => (new DeletePkpt)->handle($pkpt), 'pkpt', $msg);
        $this->assertRejected(fn () => (new SahkanPkpt)->handle($pkpt, User::factory()->admin()->create()), 'pkpt', $msg);
        $this->assertDatabaseHas('pkpt_item', ['id' => $item->id]);
    }

    public function test_draf_item_update_delete_and_pkpt_delete_cascades(): void
    {
        $pkpt = Pkpt::factory()->create(['tahun' => 2026]);
        $item = (new SavePkptItem)->handle($pkpt, null, $this->itemData());
        (new SavePkptItem)->handle($pkpt, $item, $this->itemData(['keterangan' => 'Baru']));
        $this->assertSame('Baru', $item->fresh()->keterangan);

        $other = (new SavePkptItem)->handle($pkpt, null, $this->itemData());
        (new DeletePkptItem)->handle($other);
        $this->assertDatabaseMissing('pkpt_item', ['id' => $other->id]);

        (new DeletePkpt)->handle($pkpt);
        $this->assertDatabaseMissing('pkpt', ['id' => $pkpt->id]);
        $this->assertDatabaseMissing('pkpt_item', ['id' => $item->id]);
    }

    public function test_sahkan_requires_sk_and_items_then_sets_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $pkpt = Pkpt::factory()->create(['tahun' => 2026]);
        $msg = 'Isi nomor dan tanggal SK Bupati sebelum mengesahkan.';

        $this->assertRejected(fn () => (new SahkanPkpt)->handle($pkpt, $admin), 'nomor_sk', $msg);
        $pkpt->update(['nomor_sk' => 'SK/1']);
        $this->assertRejected(fn () => (new SahkanPkpt)->handle($pkpt, $admin), 'nomor_sk', $msg);
        $pkpt->update(['tanggal_sk' => '2026-01-02']);
        $this->assertRejected(fn () => (new SahkanPkpt)->handle($pkpt, $admin), 'pkpt', 'PKPT belum memiliki item.');

        PkptItem::factory()->for($pkpt)->create();
        (new SahkanPkpt)->handle($pkpt, $admin);
        $fresh = $pkpt->fresh();
        $this->assertSame(StatusPkpt::Disahkan, $fresh->status);
        $this->assertNotNull($fresh->disahkan_pada);
        $this->assertSame($admin->id, $fresh->disahkan_oleh);
    }

    public function test_rentang_is_the_item_dates_never_the_snapshot(): void
    {
        $svc = new KesesuaianPkpt;
        $item = PkptItem::factory()->create(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']);
        $fmt = fn () => array_map(fn ($d) => $d->toDateString(), $svc->rentang($item));

        $this->assertSame(['2026-03-02', '2026-03-20'], $fmt());

        $item->update(['tanggal_mulai_disahkan' => $item->tanggal_mulai, 'tanggal_selesai_disahkan' => $item->tanggal_selesai, 'tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-20']);
        $this->assertSame(['2026-06-01', '2026-06-20'], $fmt());
        $this->assertSame('2026-03-02', $item->fresh()->tanggal_mulai_disahkan->toDateString());
    }

    public function test_sesuai_is_inclusive_and_uses_the_moved_range(): void
    {
        $svc = new KesesuaianPkpt;
        $item = PkptItem::factory()->create(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']);

        $this->assertTrue($svc->sesuai($item, '2026-03-02', '2026-03-20'));
        $this->assertTrue($svc->sesuai($item, '2026-03-05', '2026-03-06'));
        $this->assertFalse($svc->sesuai($item, '2026-03-01', '2026-03-20'));
        $this->assertFalse($svc->sesuai($item, '2026-03-02', '2026-03-21'));

        $item->update(['tanggal_mulai_disahkan' => $item->tanggal_mulai, 'tanggal_selesai_disahkan' => $item->tanggal_selesai, 'tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-20']);
        $this->assertFalse($svc->sesuai($item, '2026-03-02', '2026-03-20'));
        $this->assertTrue($svc->sesuai($item, '2026-04-01', '2026-04-20'));
    }

    public function test_irban_and_jenis_referenced_by_pkpt_item_cannot_be_deleted(): void
    {
        $item = PkptItem::factory()->create();
        $this->assertTrue($item->irban->isReferenced());
        $this->assertTrue($item->jenisPenugasan->isReferenced());
        $this->assertRejected(fn () => (new DeleteIrban)->handle($item->irban), 'delete', 'Irban ini sudah dipakai di data lain dan tidak dapat dihapus.');
        $this->assertRejected(fn () => (new DeleteJenisPenugasan)->handle($item->jenisPenugasan), 'delete', 'Jenis penugasan ini sudah dipakai di data lain dan tidak dapat dihapus.');
        $this->assertFalse(JenisPenugasan::factory()->create()->isReferenced());
    }

    public function test_seeder_builds_current_year_disahkan_and_next_year_draf(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotent

        $now = Pkpt::where('tahun', now()->year)->firstOrFail();
        $this->assertSame(StatusPkpt::Disahkan, $now->status);
        $this->assertSame('100.3.3.2/123/KPTS/INSPEKTORAT/'.now()->year, $now->nomor_sk);
        $this->assertSame(now()->year.'-01-02', $now->tanggal_sk->toDateString());
        foreach (['Irban I', 'Irban II'] as $nama) {
            $this->assertGreaterThanOrEqual(2, $now->items()->where('irban_id', Irban::where('nama', $nama)->value('id'))->count());
        }

        $next = Pkpt::where('tahun', now()->year + 1)->firstOrFail();
        $this->assertSame(StatusPkpt::Draf, $next->status);
        $this->assertSame(2, $next->items()->count());
    }
}
