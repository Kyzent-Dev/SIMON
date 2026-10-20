<?php

namespace App\Jobs;

use App\Models\Ekspor as EksporModel;
use App\Notifications\EksporGagal;
use App\Notifications\EksporSiap;
use App\Services\Ekspor;
use App\Services\PenerimaNotifikasi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BuatEkspor implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public EksporModel $ekspor) {}

    public function handle(Ekspor $layanan, PenerimaNotifikasi $penerima): void
    {
        $e = $this->ekspor;
        // A large PDF needs far more than the web 128M; raise the limits for this job only (time below $timeout), then restore them for the worker.
        [$memori, $waktu] = [ini_get('memory_limit'), (int) ini_get('max_execution_time')];
        if ($memori !== '-1' && ini_parse_quantity($memori) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }
        set_time_limit(570);
        try {
            $layanan->tulis($e);
        } catch (Throwable $ex) {
            $this->failed($ex);

            return;
        } finally {
            ini_set('memory_limit', $memori);
            set_time_limit($waktu);
        }
        Notification::send($penerima->pengguna($e->user), new EksporSiap($e));
    }

    /** Also called by the queue on time-out or kill; notifies once (only while still menunggu) and drops a partial file. */
    public function failed(Throwable $ex): void
    {
        report($ex);
        $e = $this->ekspor->fresh();
        if ($e === null || $e->status !== 'menunggu') {
            return;
        }
        Storage::disk('local')->delete("ekspor/{$e->id}.{$e->format}");
        $e->update(['status' => 'gagal']);
        Notification::send(app(PenerimaNotifikasi::class)->pengguna($e->user), new EksporGagal($e->nama_file, app(Ekspor::class)->pembangun($e->jenis)->halaman()));
    }
}
