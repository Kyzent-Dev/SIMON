<?php

namespace Tests\Feature\Pkpt;

use App\Enums\AksiPerubahan;
use App\Enums\AksiUsulan;
use App\Enums\StatusPerubahan;
use App\Enums\StatusUsulan;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\PkptUsulan;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 9.2 Task 5 (spec §4.5): the irreversible migration of pkpt_item_revisi into Perubahan PKPT / usulan.
 * Fixture: item A with two approved revisions (decided out of id order), B with one pending, C with one rejected, D in a Draf PKPT.
 */
class MigrasiRevisiJadwalTest extends TestCase
{
    use RefreshDatabase;

    private function migrasi(): object
    {
        return require database_path('migrations/2026_11_20_000020_migrasi_revisi_jadwal.php');
    }

    /** The table as 2026_10_19_000009 created it (already dropped by 000020 in the test database). */
    private function buatTabelLama(): void
    {
        Schema::create('pkpt_item_revisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pkpt_item_id')->constrained('pkpt_item')->cascadeOnDelete();
            $table->date('tanggal_mulai_semula');
            $table->date('tanggal_selesai_semula');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('status')->default('menunggu');
            $table->foreignId('diajukan_oleh')->constrained('users')->restrictOnDelete();
            $table->foreignId('diinput_oleh')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('diputuskan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('komentar')->nullable();
            $table->timestamps();
        });
    }

    public function test_migrates_revisions_and_drops_the_table(): void
    {
        // MySQL commits the RefreshDatabase transaction on DDL, so this test's rows are not rolled back: force the next
        // RefreshDatabase test to migrate:fresh (Laravel also does this when it finds no open transaction; made explicit here).
        // Not DatabaseTruncation: it truncates the migration-seeded rows (klasifikasi) at setUp and leaves this test's rows behind.
        $this->beforeApplicationDestroyed(fn () => RefreshDatabaseState::$migrated = false);
        $this->buatTabelLama();
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $draf = Pkpt::factory()->create(['tahun' => 2027]);
        $item = fn (string $m, string $s, array $x = []) => PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s] + $x);
        $a = $item('2026-03-02', '2026-03-20', ['keterangan' => 'Ket A', 'target_spt' => 2]);
        $b = $item('2026-05-04', '2026-05-22');
        $c = $item('2026-07-01', '2026-07-31');
        $d = PkptItem::factory()->create(['pkpt_id' => $draf->id, 'tanggal_mulai' => '2027-02-01', 'tanggal_selesai' => '2027-02-10']);
        $irban = User::factory()->irban()->create();
        $pimpinan = User::factory()->pimpinan()->create();
        $pegawai = Pegawai::factory()->create();

        $revisi = fn (PkptItem $i, array $x) => DB::table('pkpt_item_revisi')->insertGetId($x + [
            'pkpt_item_id' => $i->id, 'tanggal_mulai_semula' => $i->tanggal_mulai->toDateString(), 'tanggal_selesai_semula' => $i->tanggal_selesai->toDateString(),
            'diajukan_oleh' => $irban->id, 'diinput_oleh' => $pegawai->id, 'created_at' => '2026-02-01 08:00:00', 'updated_at' => '2026-02-01 08:00:00',
        ]);
        // A: the later decision has the lower id, so order must follow diputuskan_pada, not id.
        $akhir = $revisi($a, ['tanggal_mulai_semula' => '2026-04-06', 'tanggal_selesai_semula' => '2026-04-24', 'tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-19',
            'alasan' => 'Geser kedua', 'status' => 'disetujui', 'diputuskan_oleh' => $pimpinan->id, 'diputuskan_pada' => '2026-03-10 09:00:00', 'komentar' => 'Oke kedua']);
        $awal = $revisi($a, ['tanggal_mulai' => '2026-04-06', 'tanggal_selesai' => '2026-04-24',
            'alasan' => 'Geser pertama', 'status' => 'disetujui', 'diputuskan_oleh' => $pimpinan->id, 'diputuskan_pada' => '2026-02-05 09:00:00']);
        $revisi($b, ['tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-21', 'alasan' => 'Usul B', 'status' => 'menunggu']);
        $revisi($c, ['tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-30', 'alasan' => 'Usul C', 'status' => 'ditolak',
            'diputuskan_oleh' => $pimpinan->id, 'diputuskan_pada' => '2026-02-07 09:00:00', 'komentar' => 'Tidak dapat digeser.']);

        $this->migrasi()->up();

        $this->assertFalse(Schema::hasTable('pkpt_item_revisi'));

        // Snapshot = original dates (Disahkan only); A moved to the last approved revision; B and C unchanged.
        $a->refresh();
        $this->assertSame(['2026-03-02', '2026-03-20', '2026-06-01', '2026-06-19'], [$a->tanggal_mulai_disahkan->toDateString(), $a->tanggal_selesai_disahkan->toDateString(),
            $a->tanggal_mulai->toDateString(), $a->tanggal_selesai->toDateString()]);
        $this->assertTrue($a->direvisi());
        $this->assertSame(['2026-05-04', '2026-05-04'], [$b->fresh()->tanggal_mulai->toDateString(), $b->fresh()->tanggal_mulai_disahkan->toDateString()]);
        $this->assertFalse($c->fresh()->direvisi());
        $this->assertNull($d->fresh()->tanggal_mulai_disahkan);

        $paket = PkptPerubahan::orderBy('nomor_urut')->get();
        $this->assertCount(2, $paket);
        $this->assertSame([1, 2], $paket->pluck('nomor_urut')->all());
        $this->assertSame(['Geser pertama', 'Geser kedua'], $paket->pluck('alasan')->all());
        [$p1, $p2] = $paket;
        foreach ($paket as $p) {
            $this->assertSame([$pkpt->id, 'migrasi_revisi', StatusPerubahan::Disetujui, null, $pimpinan->id, null],
                [$p->pkpt_id, $p->sumber, $p->status, $p->dibuat_oleh, $p->diputuskan_oleh, $p->pengingat_sk_dimatikan_oleh]);
            $this->assertNotNull($p->pengingat_sk_dimatikan_pada);
            $this->assertSame('2026-02-01 08:00:00', $p->diajukan_pada->format('Y-m-d H:i:s'));
        }
        $this->assertSame('2026-02-05 09:00:00', $p1->pengingat_sk_dimatikan_pada->format('Y-m-d H:i:s'));
        $this->assertSame('Oke kedua', $p2->komentar_putusan);

        $op = $p1->operasi()->sole();
        $this->assertSame([AksiPerubahan::Ubah, $a->id, 1], [$op->aksi, $op->pkpt_item_id, $op->urutan]);
        $dasar = ['irban_id' => $a->irban_id, 'jenis_penugasan_id' => $a->jenis_penugasan_id, 'keterangan' => 'Ket A', 'target_spt' => 2];
        $this->assertEquals($dasar + ['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20'], $op->data_lama);
        $this->assertEquals($dasar + ['tanggal_mulai' => '2026-04-06', 'tanggal_selesai' => '2026-04-24'], $op->data_baru);
        $op2 = $p2->operasi()->sole();
        $this->assertEquals($dasar + ['tanggal_mulai' => '2026-04-06', 'tanggal_selesai' => '2026-04-24'], $op2->data_lama);
        $this->assertEquals($dasar + ['tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-19'], $op2->data_baru);

        $riwayat = $p1->riwayat()->sole();
        $this->assertSame(['disetujui', $pimpinan->id, "Dimigrasikan dari revisi jadwal #{$awal}"], [$riwayat->kejadian, $riwayat->oleh, $riwayat->catatan]);
        $this->assertSame("Dimigrasikan dari revisi jadwal #{$akhir}", $p2->riwayat()->sole()->catatan);

        $usulanB = PkptUsulan::where('pkpt_item_id', $b->id)->sole();
        $this->assertSame([StatusUsulan::Terbuka, AksiUsulan::Geser, $pkpt->id, $b->irban_id, '2026-08-03', '2026-08-21', 'Usul B', $irban->id, $pegawai->id, null],
            [$usulanB->status, $usulanB->aksi_usulan, $usulanB->pkpt_id, $usulanB->irban_id, $usulanB->tanggal_mulai->toDateString(), $usulanB->tanggal_selesai->toDateString(),
                $usulanB->alasan, $usulanB->diajukan_oleh, $usulanB->diinput_oleh, $usulanB->catatan_penutupan]);
        $this->assertSame('2026-02-01 08:00:00', $usulanB->created_at->format('Y-m-d H:i:s'));
        $usulanC = PkptUsulan::where('pkpt_item_id', $c->id)->sole();
        $this->assertSame([StatusUsulan::Ditutup, 'Tidak dapat digeser.', $pimpinan->id], [$usulanC->status, $usulanC->catatan_penutupan, $usulanC->ditutup_oleh]);
        $this->assertSame(2, PkptUsulan::count());
    }

    public function test_down_throws(): void
    {
        $migrasi = $this->migrasi();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Migrasi revisi jadwal tidak dapat dibalik.');
        $migrasi->down();
    }
}
