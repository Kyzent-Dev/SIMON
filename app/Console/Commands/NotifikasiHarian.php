<?php

namespace App\Console\Commands;

use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Notifications\PengingatSptFinal;
use App\Notifications\SptTerlambat;
use App\Services\PenandaSpt;
use App\Services\PenerimaNotifikasi;
use App\Services\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifikasiHarian extends Command
{
    protected $signature = 'notifikasi:harian';

    protected $description = 'Kirim notifikasi SPT terlambat dan pengingat SPT final (sekali per SPT).';

    public function handle(PenandaSpt $penanda, PenerimaNotifikasi $penerima, Settings $settings): int
    {
        $batas = (int) $settings->get('penanda.batas_terlambat_hari');
        $hariIni = now('Asia/Jakarta')->startOfDay();
        $terlambat = $pengingat = 0;

        Spt::with('pkptItem')->where('status_pengajuan', StatusPengajuan::Diterima)->whereNull('ditandai_selesai_pada')
            ->where(fn ($q) => $q->whereNull('terlambat_diberitahu_pada')->orWhereNull('pengingat_final_pada'))
            ->chunkById(200, function ($semua) use (&$terlambat, &$pengingat, $penanda, $penerima, $batas, $hariIni) {
                foreach ($semua as $spt) {
                    if ($spt->terlambat_diberitahu_pada === null && in_array('terlambat', $penanda->untuk($spt), true)) {
                        Notification::send($penerima->irban($spt->irban_id), new SptTerlambat($spt, $batas));
                        $spt->forceFill(['terlambat_diberitahu_pada' => now()])->save();
                        $terlambat++;
                    }
                    if ($spt->pengingat_final_pada === null && $spt->tanggal_mulai !== null
                        && $hariIni->toDateString() >= $spt->tanggal_mulai->addDays(3)->toDateString()
                        && ! $spt->dokumen()->where('jenis', 'final')->exists()) {
                        Notification::send($penerima->irban($spt->irban_id), new PengingatSptFinal($spt));
                        $spt->forceFill(['pengingat_final_pada' => now()])->save();
                        $pengingat++;
                    }
                }
            });

        $this->info("Terlambat: {$terlambat} notifikasi, pengingat SPT final: {$pengingat} notifikasi.");

        return self::SUCCESS;
    }
}
