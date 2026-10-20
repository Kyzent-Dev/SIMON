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
        try {
            $layanan->tulis($e);
        } catch (Throwable $ex) {
            report($ex);
            $e->update(['status' => 'gagal']);
            Notification::send($penerima->pengguna($e->user), new EksporGagal($e->nama_file, $layanan->pembangun($e->jenis)->halaman()));

            return;
        }
        Notification::send($penerima->pengguna($e->user), new EksporSiap($e));
    }
}
