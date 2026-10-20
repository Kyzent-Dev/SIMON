<?php

namespace App\Console\Commands;

use App\Notifications\RingkasanMingguan;
use App\Services\PenerimaNotifikasi;
use App\Services\RingkasanMingguan as Hitung;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifikasiRingkasanMingguan extends Command
{
    protected $signature = 'notifikasi:ringkasan-mingguan';

    protected $description = 'Kirim ringkasan mingguan ke pimpinan.';

    public function handle(Hitung $hitung, PenerimaNotifikasi $penerima): int
    {
        $senin = CarbonImmutable::now('Asia/Jakarta')->startOfWeek(CarbonInterface::MONDAY); // explicit: the default follows the locale
        $pimpinan = $penerima->pimpinan();
        if ($pimpinan->isEmpty()) {
            $this->warn('Tidak ada pimpinan penerima; ringkasan tidak dikirim.');

            return self::SUCCESS;
        }
        Notification::send($pimpinan, new RingkasanMingguan($hitung->hitung($senin)));
        $this->info("Ringkasan mingguan terkirim ke {$pimpinan->count()} pimpinan.");

        return self::SUCCESS;
    }
}
