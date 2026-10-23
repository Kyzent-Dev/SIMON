<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use Illuminate\Support\Facades\DB;
use Tests\Support\KonkurensiProses;
use Tests\TestCase;

/** Fase 9.2 Task 6: approvals running at the same time in separate processes (PKPT row lock, nomor_urut, status re-check). */
class PerubahanPkptKonkurensiTest extends TestCase
{
    use KonkurensiProses;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanKonkurensi();
    }

    protected function tearDown(): void
    {
        $this->bersihkanKonkurensi();
        parent::tearDown();
    }

    /**
     * A Disahkan PKPT 2026 with one item per package, each package `diajukan` with one `ubah` operation; no other users, so no notification.
     *
     * @return array{0: int, 1: list<int>} pimpinan id, package ids
     */
    private function siapkan(int $paket): array
    {
        $this->migrasiKonkurensi();
        $db = DB::connection('konkurensi');
        $now = now();
        $pimpinan = $db->table('users')->insertGetId(['name' => 'Pimpinan', 'username' => 'pimpinan', 'email' => 'p@example.test', 'password' => 'x',
            'role' => 'pimpinan', 'created_at' => $now, 'updated_at' => $now]);
        $irban = $db->table('irban')->insertGetId(['nama' => 'Irban I', 'kode_nomor' => 'INSPEKTORAT-I', 'created_at' => $now, 'updated_at' => $now]);
        $jenis = $db->table('jenis_penugasan')->insertGetId(['nama' => 'Reviu', 'warna' => '#17735B', 'warna_teks' => '#FFFFFF', 'created_at' => $now, 'updated_at' => $now]);
        $pkpt = $db->table('pkpt')->insertGetId(['tahun' => 2026, 'status' => 'disahkan', 'disahkan_pada' => $now, 'created_at' => $now, 'updated_at' => $now]);

        $ids = [];
        for ($i = 1; $i <= $paket; $i++) {
            $item = $db->table('pkpt_item')->insertGetId(['pkpt_id' => $pkpt, 'irban_id' => $irban, 'jenis_penugasan_id' => $jenis,
                'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20', 'tanggal_mulai_disahkan' => '2026-03-02',
                'tanggal_selesai_disahkan' => '2026-03-20', 'status' => 'aktif', 'created_at' => $now, 'updated_at' => $now]);
            $ids[] = $id = $db->table('pkpt_perubahan')->insertGetId(['pkpt_id' => $pkpt, 'jenis' => 'perubahan', 'alasan' => "Paket {$i}",
                'status' => 'diajukan', 'sumber' => 'admin', 'diajukan_pada' => $now, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('pkpt_perubahan_item')->insert(['perubahan_id' => $id, 'aksi' => 'ubah', 'pkpt_item_id' => $item, 'urutan' => 1,
                'data_baru' => json_encode(['irban_id' => $irban, 'jenis_penugasan_id' => $jenis, 'tanggal_mulai' => '2026-04-0'.$i,
                    'tanggal_selesai' => '2026-04-20', 'keterangan' => null, 'target_spt' => null]), 'created_at' => $now, 'updated_at' => $now]);
        }

        return [$pimpinan, $ids];
    }

    public function test_two_packages_of_one_pkpt_approved_at_once_get_numbers_one_and_two(): void
    {
        [$pimpinan, $ids] = $this->siapkan(2);
        $hasil = $this->jalankan('tests/Support/putuskan-perubahan.php', array_map(fn ($id) => [(string) $id, (string) $pimpinan, $this->barrier], $ids));

        $this->assertSame([true, true], array_column($hasil, 'ok'));
        $urut = array_column($hasil, 'nomor_urut');
        sort($urut);
        $this->assertSame([1, 2], $urut);
        $db = DB::connection('konkurensi');
        $this->assertEqualsCanonicalizing([1, 2], $db->table('pkpt_perubahan')->pluck('nomor_urut')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(2, $db->table('pkpt_perubahan')->where('status', 'disetujui')->count());
        $this->assertSame(2, $db->table('pkpt_item')->whereDate('tanggal_selesai', '2026-04-20')->count());
        $this->assertSame(2, $db->table('pkpt_perubahan_riwayat')->where('kejadian', 'disetujui')->count());
    }

    public function test_the_same_package_approved_twice_at_once_succeeds_exactly_once(): void
    {
        [$pimpinan, $ids] = $this->siapkan(1);
        $hasil = $this->jalankan('tests/Support/putuskan-perubahan.php', array_fill(0, 2, [(string) $ids[0], (string) $pimpinan, $this->barrier]));

        $ok = array_values(array_filter($hasil, fn ($h) => $h['ok']));
        $gagal = array_values(array_filter($hasil, fn ($h) => ! $h['ok']));
        $this->assertCount(1, $ok);
        $this->assertSame(1, $ok[0]['nomor_urut']);
        $this->assertCount(1, $gagal);
        $this->assertSame(PutuskanPerubahan::SUDAH_DIPUTUSKAN, $gagal[0]['pesan']);
        $db = DB::connection('konkurensi');
        $this->assertSame(1, $db->table('pkpt_perubahan_riwayat')->where('kejadian', 'disetujui')->count());
        $this->assertNotNull($db->table('pkpt_perubahan_item')->value('data_lama'));
    }
}
