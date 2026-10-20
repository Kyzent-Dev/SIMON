<?php

namespace App\Console\Commands;

use App\Notifications\RingkasanMingguan;
use App\Services\PenerimaNotifikasi;
use App\Services\RingkasanMingguan as Hitung;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifikasiRingkasanMingguan extends Command
{
    protected $signature = 'notifikasi:ringkasan-mingguan';

    protected $description = 'Kirim ringkasan mingguan ke pimpinan.';

    public function handle(Hitung $hitung, PenerimaNotifikasi $penerima): int
    {
        $senin = CarbonImmutable::now('Asia/Jakarta')->startOfWeek();
        Notification::send($penerima->pimpinan(), new RingkasanMingguan($hitung->hitung($senin)));
        $this->info('Ringkasan mingguan terkirim.');

        return self::SUCCESS;
    }
}
