<?php

namespace Tests\Support;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Real-concurrency harness: child PHP processes against a throwaway database (a SQLite file, or a fresh MySQL/MariaDB database when the
 * suite runs on MySQL), released together through a start barrier. The `konkurensi` connection points the test at that database.
 * Call siapkanKonkurensi() in setUp and bersihkanKonkurensi() in tearDown.
 */
trait KonkurensiProses
{
    /** SQLite file path, or the throwaway database name on MySQL/MariaDB. */
    private string $db;

    private string $barrier;

    /** Base connection config when the suite runs on MySQL/MariaDB, so the locking is tested on the production driver; null = SQLite. */
    private ?array $mysql = null;

    private function siapkanKonkurensi(): void
    {
        if (! function_exists('proc_open')) {
            $this->markTestSkipped('proc_open tidak tersedia.');
        }
        $base = config('database.connections.'.config('database.default'));
        $file = tempnam(sys_get_temp_dir(), 'sintas-konkurensi-');
        $this->barrier = $file.'-barrier';
        mkdir($this->barrier);
        if (in_array($base['driver'], ['mysql', 'mariadb'], true)) {
            // Children are separate processes, so they cannot share the test transaction: give them their own database.
            @unlink($file);
            $this->mysql = $base;
            $this->db = 'sintas_konkurensi_'.bin2hex(random_bytes(4));
            $this->admin()->statement("CREATE DATABASE `{$this->db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } else {
            $this->db = $file;
        }
    }

    private function bersihkanKonkurensi(): void
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

    /** Migrates the throwaway database and points the `konkurensi` connection at it. */
    private function migrasiKonkurensi(): void
    {
        (new Process([PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], base_path(), $this->env(), null, 120))->mustRun();
        $this->hubungkan();
    }

    /**
     * Starts one child per argument list, releases them together through the start barrier, and returns their decoded output.
     * The script gets the barrier dir as its third argument: after booting it touches {dir}/siap-{pid} and waits for {dir}/mulai.
     *
     * @param  list<list<string>>  $argumen
     * @return list<array<string, mixed>>
     */
    private function jalankan(string $skrip, array $argumen): array
    {
        DB::purge('konkurensi');
        $proses = [];
        foreach ($argumen as $args) {
            $p = new Process([PHP_BINARY, base_path($skrip), ...$args], base_path(), $this->env(), null, 120);
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
}
