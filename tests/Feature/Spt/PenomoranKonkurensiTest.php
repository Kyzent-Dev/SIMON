<?php

namespace Tests\Feature\Spt;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** PRD Fase 4 acceptance: 20 processes taking a number at once get 20 unique, sequential numbers. */
class PenomoranKonkurensiTest extends TestCase
{
    private const PROSES = 20;

    private string $db;

    protected function setUp(): void
    {
        parent::setUp();
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open tidak tersedia.');
        }
        $this->db = tempnam(sys_get_temp_dir(), 'simon-konkurensi-');
    }

    protected function tearDown(): void
    {
        DB::purge('konkurensi');
        @unlink($this->db);
        parent::tearDown();
    }

    /** Env for child processes: same app, file-based SQLite. */
    private function env(): array
    {
        return ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->db, 'DB_URL' => '', 'APP_ENV' => 'testing', 'CACHE_STORE' => 'array'];
    }

    public function test_twenty_concurrent_processes_get_twenty_sequential_numbers(): void
    {
        $migrate = new Process([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], base_path(), $this->env(), null, 120);
        $migrate->mustRun();

        config(['database.connections.konkurensi' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => true]]);
        $irbanId = DB::connection('konkurensi')->table('irban')->insertGetId([
            'nama' => 'Irban II', 'kode_nomor' => 'INSPEKTORAT-II', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::purge('konkurensi');

        $proses = [];
        for ($i = 0; $i < self::PROSES; $i++) {
            $p = new Process([PHP_BINARY, base_path('tests/Support/ambil-nomor.php'), (string) $irbanId, '2026-05-04'], base_path(), $this->env(), null, 120);
            $p->start();
            $proses[] = $p;
        }

        $hasil = [];
        foreach ($proses as $p) {
            $p->wait();
            $this->assertSame(0, $p->getExitCode(), $p->getErrorOutput().$p->getOutput());
            $hasil[] = json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        }

        $urut = array_column($hasil, 'nomor_urut');
        sort($urut);
        $this->assertSame(range(1, self::PROSES), $urut);
        $lengkap = array_column($hasil, 'nomor_lengkap');
        $this->assertCount(self::PROSES, array_unique($lengkap));
        $this->assertContains('700/20/INSPEKTORAT-II/SPT/2026', $lengkap);

        config(['database.connections.konkurensi' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '']]);
        $this->assertSame(self::PROSES, (int) DB::connection('konkurensi')->table('nomor_counter')->value('nomor_terakhir'));
        $this->assertSame(1, DB::connection('konkurensi')->table('nomor_counter')->count());
    }
}
