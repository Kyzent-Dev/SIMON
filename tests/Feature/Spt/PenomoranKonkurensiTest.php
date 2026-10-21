<?php

namespace Tests\Feature\Spt;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** PRD Fase 4 acceptance: 20 processes taking a number at once get 20 unique, sequential numbers. */
class PenomoranKonkurensiTest extends TestCase
{
    private const PROSES = 20;

    /** SQLite file path, or the throwaway database name on MySQL/MariaDB. */
    private string $db;

    private string $barrier;

    /** Base connection config when the suite runs on MySQL/MariaDB, so the locking is tested on the production driver; null = SQLite. */
    private ?array $mysql = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open tidak tersedia.');
        }
        $base = config('database.connections.'.config('database.default'));
        $file = tempnam(sys_get_temp_dir(), 'simon-konkurensi-');
        $this->barrier = $file.'-barrier';
        mkdir($this->barrier);
        if (in_array($base['driver'], ['mysql', 'mariadb'], true)) {
            // Children are separate processes, so they cannot share the test transaction: give them their own database.
            @unlink($file);
            $this->mysql = $base;
            $this->db = 'simon_konkurensi_'.bin2hex(random_bytes(4));
            $this->admin()->statement("CREATE DATABASE `{$this->db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } else {
            $this->db = $file;
        }
    }

    protected function tearDown(): void
    {
        DB::purge('konkurensi');
        if ($this->mysql) {
            $this->admin()->statement("DROP DATABASE IF EXISTS `{$this->db}`");
            DB::purge('konkurensi_admin');
        } else {
            @unlink($this->db);
        }
        array_map('unlink', glob($this->barrier.'/*') ?: []);
        @rmdir($this->barrier);
        parent::tearDown();
    }

    /** Separate MySQL connection for CREATE/DROP DATABASE: DDL commits implicitly, so it must stay off the test's connection. */
    private function admin(): ConnectionInterface
    {
        config(['database.connections.konkurensi_admin' => $this->mysql]);

        return DB::connection('konkurensi_admin');
    }

    /** Env for child processes: same app, the throwaway database on the suite's driver. */
    private function env(): array
    {
        $env = ['DB_DATABASE' => $this->db, 'DB_URL' => '', 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array'];

        return $this->mysql
            ? $env + ['DB_CONNECTION' => $this->mysql['driver'], 'DB_HOST' => $this->mysql['host'], 'DB_PORT' => (string) $this->mysql['port'],
                'DB_USERNAME' => $this->mysql['username'], 'DB_PASSWORD' => (string) $this->mysql['password']]
            : $env + ['DB_CONNECTION' => 'sqlite'];
    }

    /** Points the `konkurensi` connection at the throwaway database. */
    private function hubungkan(bool $foreignKeys = true): void
    {
        DB::purge('konkurensi');
        config(['database.connections.konkurensi' => $this->mysql
            ? ['database' => $this->db] + $this->mysql
            : ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => $foreignKeys]]);
    }

    /** Migrates the throwaway database, points the `konkurensi` connection at it and inserts the irban; returns its id. */
    private function siapkan(): int
    {
        $migrate = new Process([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], base_path(), $this->env(), null, 120);
        $migrate->mustRun();

        $this->hubungkan();

        return DB::connection('konkurensi')->table('irban')->insertGetId([
            'nama' => 'Irban II', 'kode_nomor' => 'INSPEKTORAT-II', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Starts one child per argument list, releases them together through the start barrier, and returns their decoded output.
     *
     * @param  list<list<string>>  $argumen
     * @return list<array{nomor_urut: int, nomor_lengkap: string}>
     */
    private function jalankan(array $argumen): array
    {
        DB::purge('konkurensi');
        $proses = [];
        foreach ($argumen as $args) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/ambil-nomor.php'), ...$args], base_path(), $this->env(), null, 120);
            $p->start();
            $proses[] = $p;
        }

        // Start barrier: release all children only once every one has booted, so the transactions overlap every run.
        for ($batas = microtime(true) + 90; count(glob($this->barrier.'/siap-*')) < count($proses); usleep(10000)) {
            foreach ($proses as $p) {
                $this->assertTrue($p->isRunning() || $p->getExitCode() === 0, 'Proses berhenti sebelum barrier: '.$p->getErrorOutput().$p->getOutput());
            }
            $this->assertLessThan($batas, microtime(true), 'Barrier timeout');
        }
        touch($this->barrier.'/mulai');

        $hasil = [];
        foreach ($proses as $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
            $hasil[] = json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->hubungkan(foreignKeys: false);

        return $hasil;
    }

    public function test_twenty_concurrent_processes_get_twenty_sequential_numbers(): void
    {
        $irbanId = $this->siapkan();
        $hasil = $this->jalankan(array_fill(0, self::PROSES, [(string) $irbanId, '2026-05-04', $this->barrier]));

        $urut = array_column($hasil, 'nomor_urut');
        sort($urut);
        $this->assertSame(range(1, self::PROSES), $urut);
        $lengkap = array_column($hasil, 'nomor_lengkap');
        $this->assertCount(self::PROSES, array_unique($lengkap));
        $this->assertContains('700/20/INSPEKTORAT-II/SPT/2026', $lengkap);

        $this->assertSame(self::PROSES, (int) DB::connection('konkurensi')->table('nomor_counter')->value('nomor_terakhir'));
        $this->assertSame(1, DB::connection('konkurensi')->table('nomor_counter')->count());
    }

    /** PRD §14 #2 at decision level: Bagian Umum accepting different SPT from several processes at once gets distinct consecutive numbers. */
    public function test_concurrent_terima_pengajuan_on_different_spt(): void
    {
        $irbanId = $this->siapkan();
        $db = DB::connection('konkurensi');
        $userId = $db->table('users')->insertGetId(['name' => 'Bagian Umum', 'username' => 'bu', 'email' => 'bu@example.test', 'password' => 'x',
            'role' => 'bagian_umum', 'created_at' => now(), 'updated_at' => now()]);
        $klasifikasi = $db->table('klasifikasi')->where('kode', 'KHUSUS')->value('id');
        $sptIds = [];
        for ($i = 0; $i < 5; $i++) {
            $sptIds[] = $db->table('spt')->insertGetId(['irban_id' => $irbanId, 'klasifikasi_id' => $klasifikasi, 'status_pengajuan' => 'menunggu_nomor',
                'diajukan_pada' => now(), 'dibuat_oleh' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        }

        $hasil = $this->jalankan(array_map(fn ($id) => [(string) $irbanId, '', $this->barrier, (string) $id, (string) $userId], $sptIds));

        $urut = array_column($hasil, 'nomor_urut');
        sort($urut);
        $this->assertSame(range(1, 5), $urut);
        $db = DB::connection('konkurensi');
        $this->assertSame(5, $db->table('spt')->where('status_pengajuan', 'diterima')->distinct()->count('nomor_lengkap'));
        $this->assertSame(5, (int) $db->table('nomor_counter')->value('nomor_terakhir'));
        $this->assertSame(5, $db->table('spt_riwayat')->where('kejadian', 'diterima')->count());
    }
}
