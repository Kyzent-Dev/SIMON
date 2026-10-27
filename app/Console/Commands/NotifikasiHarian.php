<?php

namespace App\Console\Commands;

use App\Enums\StatusPengajuan;
use App\Enums\StatusPerubahan;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Notifications\PengingatPerubahanPkpt;
use App\Notifications\PengingatSkPerubahan;
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

    protected $description = 'Kirim notifikasi SPT terlambat dan pengingat SPT final (sekali per SPT), serta pengingat harian Perubahan PKPT dan SK-nya.';

    public function handle(PenandaSpt $penanda, PenerimaNotifikasi $penerima, Settings $settings): int
    {
        $batas = (int) $settings->get('penanda.batas_terlambat_hari');
        $hariIni = now('Asia/Jakarta')->startOfDay();
        $terlambat = $pengingat = 0;

        Spt::with('pkptItem')->where('status_pengajuan', StatusPengajuan::Diterima)->whereNull('ditandai_selesai_pada')
            // Only rows that can still trigger: a pengingat needs a tanggal_mulai and no final yet (the in-loop checks decide who is notified).
            ->where(fn ($q) => $q->whereNull('terlambat_diberitahu_pada')
                ->orWhere(fn ($q) => $q->whereNull('pengingat_final_pada')->whereNotNull('tanggal_mulai')
                    ->whereDoesntHave('dokumen', fn ($d) => $d->where('jenis', 'final'))))
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

        // Perubahan PKPT: at most one reminder per package per day (pengingat_terakhir_pada), the first one the day after submission/approval.
        $perubahan = $sk = 0;
        PkptPerubahan::with('pkpt')
            ->where(fn ($q) => $q->whereNull('pengingat_terakhir_pada')->orWhere('pengingat_terakhir_pada', '<', $hariIni))
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('status', StatusPerubahan::Diajukan)->where('diajukan_pada', '<', $hariIni))
                ->orWhere(fn ($q) => $q->perluSk()->whereNull('pengingat_sk_dimatikan_pada')->where('diputuskan_pada', '<', $hariIni)))
            ->chunkById(100, function ($semua) use (&$perubahan, &$sk, $penerima) {
                foreach ($semua as $p) {
                    if ($p->status === StatusPerubahan::Diajukan) {
                        Notification::send($penerima->pimpinan(), new PengingatPerubahanPkpt($p));
                        $perubahan++;
                    } else {
                        Notification::send($penerima->admin(), new PengingatSkPerubahan($p));
                        $sk++;
                    }
                    $p->forceFill(['pengingat_terakhir_pada' => now()])->save();
                }
            });

        $this->info("Terlambat: {$terlambat} notifikasi, pengingat SPT final: {$pengingat} notifikasi, "
            ."pengingat perubahan PKPT: {$perubahan} notifikasi, pengingat SK perubahan: {$sk} notifikasi.");

        return self::SUCCESS;
    }
}
