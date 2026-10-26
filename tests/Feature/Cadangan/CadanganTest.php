<?php

namespace Tests\Feature\Cadangan;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Tests\TestCase;
use ZipArchive;

class CadanganTest extends TestCase
{
    private string $dirUji;

    private string $berkasPrivat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dirUji = storage_path('framework/testing/cadangan-'.uniqid());
        $this->berkasPrivat = storage_path('app/private/uji-cadangan-'.uniqid().'.txt');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dirUji);
        File::delete($this->berkasPrivat);
        parent::tearDown();
    }

    public function test_jadwal(): void
    {
        $acara = collect(app(Schedule::class)->events());
        $cari = fn (string $perintah): Event => $acara->first(fn (Event $e) => str_contains($e->command, $perintah));

        foreach (['backup:clean' => '0 1 * * *', 'backup:run' => '30 1 * * *', 'backup:monitor' => '0 8 * * *'] as $perintah => $cron) {
            $e = $cari($perintah);
            $this->assertNotNull($e, $perintah);
            $this->assertSame($cron, $e->expression, $perintah);
            $this->assertSame('Asia/Jakarta', (string) $e->timezone, $perintah);
            $this->assertTrue($e->withoutOverlapping, $perintah);
        }
    }

    public function test_konfigurasi(): void
    {
        $this->assertEqualsCanonicalizing(
            [storage_path('app/private'), storage_path('app/public')],
            config('backup.backup.source.files.include'),
        );
        $this->assertEqualsCanonicalizing(
            [storage_path('app/private/ekspor'), storage_path('app/private/livewire-tmp'), storage_path('app/backup')],
            config('backup.backup.source.files.exclude'),
        );
        $this->assertSame([config('database.default')], config('backup.backup.source.databases'));
        $this->assertSame(['backup'], config('backup.backup.destination.disks'));
        $this->assertSame('simon-', config('backup.backup.destination.filename_prefix'));
        $this->assertSame(config('app.name'), config('backup.backup.name'));

        $c = config('backup.cleanup.default_strategy');
        $this->assertSame([7, 30, 8, 12, 5000], [
            $c['keep_all_backups_for_days'], $c['keep_daily_backups_for_days'],
            $c['keep_weekly_backups_for_weeks'], $c['keep_monthly_backups_for_months'],
            $c['delete_oldest_backups_when_using_more_megabytes_than'],
        ]);
        $this->assertSame(['backup'], config('backup.monitor_backups.0.disks'));

        $this->assertSame(storage_path('app/backup'), config('filesystems.disks.backup.root'));
        $this->assertSame('local', config('filesystems.disks.backup.driver'));
        $this->assertArrayHasKey('dump_binary_path', config('database.connections.mysql.dump'));
        $this->assertArrayHasKey('dump_binary_path', config('database.connections.mariadb.dump'));
    }

    public function test_tanpa_email_tidak_gagal(): void
    {
        // BACKUP_MAIL_TO is empty in the test environment: no channel, no routing, no crash.
        $this->assertSame([], config('backup.notifications.notifications')[BackupWasSuccessfulNotification::class]);

        config(['filesystems.disks.backup.root' => $this->dirUji]);
        Storage::forgetDisk('backup');
        File::ensureDirectoryExists(dirname($this->berkasPrivat));
        File::put($this->berkasPrivat, 'isi uji cadangan');

        $this->artisan('backup:run', ['--only-files' => true, '--disable-notifications' => false])->assertExitCode(0);

        $zip = Storage::disk('backup')->allFiles();
        $this->assertCount(1, $zip);
        $this->assertStringStartsWith(config('app.name').'/simon-', $zip[0]);

        $arsip = new ZipArchive;
        $this->assertTrue($arsip->open($this->dirUji.'/'.$zip[0]) === true);
        $nama = [];
        for ($i = 0; $i < $arsip->numFiles; $i++) {
            $nama[] = str_replace('\\', '/', $arsip->getNameIndex($i)); // Windows writes backslashes
        }
        $arsip->close();
        $this->assertNotEmpty(array_filter($nama, fn ($n) => str_ends_with($n, basename($this->berkasPrivat))));
        // Relative entries (storage/app/private/...), not the server's absolute path.
        $this->assertNotEmpty(array_filter($nama, fn ($n) => str_starts_with($n, 'storage/app/private/') && str_ends_with($n, basename($this->berkasPrivat))));
    }
}
