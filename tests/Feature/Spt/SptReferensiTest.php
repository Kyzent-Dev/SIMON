<?php

namespace Tests\Feature\Spt;

use App\Actions\MasterData\DeleteIrban;
use App\Actions\MasterData\DeleteJenisPenugasan;
use App\Actions\MasterData\DeleteObjek;
use App\Actions\MasterData\DeletePegawai;
use App\Actions\Pkpt\DeletePkpt;
use App\Actions\Pkpt\DeletePkptItem;
use App\Enums\PeranTim;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Objek;
use App\Models\Pegawai;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\SptPersonel;
use App\Models\SptRiwayat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Master data used by an SPT (restrict FKs) gives the usual validation message, never a 500. */
class SptReferensiTest extends TestCase
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

    public function test_irban_used_by_spt_cannot_be_deleted(): void
    {
        $irban = Irban::factory()->create();
        Spt::factory()->for($irban)->create();

        $this->assertRejected(fn () => (new DeleteIrban)->handle($irban), 'delete', 'Irban ini sudah dipakai di data lain dan tidak dapat dihapus.');
    }

    public function test_pegawai_used_by_spt_cannot_be_deleted(): void
    {
        $pesan = 'Pegawai ini sudah dipakai di data lain dan tidak dapat dihapus. Nonaktifkan saja.';
        $kolom = ['ppj_pegawai_id', 'diinput_oleh', 'penandatangan_pegawai_id'];
        foreach ($kolom as $k) {
            $pegawai = Pegawai::factory()->create();
            Spt::factory()->create([$k => $pegawai->id]);
            $this->assertRejected(fn () => (new DeletePegawai)->handle($pegawai), 'delete', $pesan);
        }

        $personel = Pegawai::factory()->create();
        SptPersonel::create(['spt_id' => Spt::factory()->create()->id, 'pegawai_id' => $personel->id, 'peran' => PeranTim::Ketua, 'urutan' => 1, 'sumber' => 'tambahan']);
        $this->assertRejected(fn () => (new DeletePegawai)->handle($personel), 'delete', $pesan);

        $penginput = Pegawai::factory()->create();
        $spt = Spt::factory()->create();
        SptRiwayat::create(['spt_id' => $spt->id, 'kejadian' => 'dibuat', 'oleh' => $spt->dibuat_oleh, 'diinput_oleh' => $penginput->id]);
        $this->assertRejected(fn () => (new DeletePegawai)->handle($penginput), 'delete', $pesan);

        $bebas = Pegawai::factory()->create();
        (new DeletePegawai)->handle($bebas);
        $this->assertModelMissing($bebas);
    }

    public function test_jenis_objek_and_pkpt_item_used_by_spt_cannot_be_deleted(): void
    {
        $jenis = JenisPenugasan::factory()->create();
        Spt::factory()->create(['jenis_penugasan_id' => $jenis->id]);
        $this->assertRejected(fn () => (new DeleteJenisPenugasan)->handle($jenis), 'delete', 'Jenis penugasan ini sudah dipakai di data lain dan tidak dapat dihapus.');

        $objek = Objek::factory()->create();
        Spt::factory()->create()->objek()->attach($objek);
        $this->assertRejected(fn () => (new DeleteObjek)->handle($objek), 'delete', 'Objek ini sudah dipakai pada SPT; tidak dapat dihapus.');

        $item = PkptItem::factory()->create();
        Spt::factory()->create(['pkpt_item_id' => $item->id]);
        $this->assertRejected(fn () => (new DeletePkptItem)->handle($item), 'pkpt', 'Item PKPT ini sudah dipakai SPT dan tidak dapat dihapus.');
        $this->assertRejected(fn () => (new DeletePkpt)->handle($item->pkpt), 'pkpt', 'PKPT ini punya item yang sudah dipakai SPT dan tidak dapat dihapus.');
    }
}
