<?php

namespace Database\Seeders;

use App\Enums\StatusPengajuan;
use App\Enums\StatusRevisi;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\NomorDibatalkan;
use App\Notifications\NotifikasiSimon;
use App\Notifications\PengajuanBaru;
use App\Notifications\PengajuanDiputuskan;
use App\Notifications\RevisiJadwalDiajukan;
use App\Notifications\RevisiJadwalDiputuskan;
use App\Notifications\RingkasanMingguan;
use App\Notifications\SptTerlambat;
use App\Services\RingkasanMingguan as Hitung;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

// Dev only. Run after SptSeeder. Bell entries are built by the real notification classes, database channel only (no email).
class NotifikasiSeeder extends Seeder
{
    public function run(): void
    {
        $irban = User::where('username', 'irban1')->first();
        $umum = User::where('username', 'bagianumum')->first();
        $pimpinan = User::where('username', 'pimpinan')->first();
        if (app()->isProduction() || ! $irban?->irban_id || ! $umum || ! $pimpinan || $irban->notifications()->exists()) {
            return;
        }

        $spt = fn (StatusPengajuan $status, string $uraian = '%') => Spt::where('irban_id', $irban->irban_id)->where('status_pengajuan', $status)
            ->where('uraian_untuk', 'like', $uraian)->orderBy('id')->first();

        // Irban: decisions on its submissions, a cancelled number, a late SPT (marked as notified, as the daily command would).
        $this->kirim($irban, fn ($s) => new PengajuanDiputuskan($s), $spt(StatusPengajuan::Diterima), 120, false);
        $this->kirim($irban, fn ($s) => new PengajuanDiputuskan($s, 'Lengkapi uraian objek dan periode yang diperiksa.'), $spt(StatusPengajuan::PerluRevisi), 1500, true);
        $this->kirim($irban, fn ($s) => new NomorDibatalkan($s, 'Penugasan digabung ke SPT lain.'), $spt(StatusPengajuan::Dibatalkan), 4300, true);
        $terlambat = $spt(StatusPengajuan::Diterima, '%terlambat%');
        if ($this->kirim($irban, fn ($s) => new SptTerlambat($s, (int) app(Settings::class)->get('penanda.batas_terlambat_hari')), $terlambat, 30, false)) {
            $terlambat->forceFill(['terlambat_diberitahu_pada' => now()])->save();
        }

        // Bagian Umum: new submissions waiting for a number.
        Spt::where('irban_id', $irban->irban_id)->where('status_pengajuan', StatusPengajuan::MenungguNomor)->orderBy('id')->get()
            ->each(fn (Spt $s, int $i) => $this->kirim($umum, fn ($s) => new PengajuanBaru($s), $s, [10, 75, 2900][$i] ?? 4400, $i >= 2));

        // Revisi jadwal: an unsaved revision (no pending revisi row is created) only feeds the notification text.
        $item = PkptItem::with(['irban', 'jenisPenugasan'])->where('irban_id', $irban->irban_id)->orderBy('tanggal_mulai')->first();
        if ($item) {
            $revisi = (new PkptItemRevisi)->forceFill([
                'tanggal_mulai_semula' => $item->tanggal_mulai, 'tanggal_selesai_semula' => $item->tanggal_selesai,
                'tanggal_mulai' => $item->tanggal_mulai->addDays(14), 'tanggal_selesai' => $item->tanggal_selesai->addDays(14),
                'alasan' => 'Objek pemeriksaan meminta penjadwalan ulang.', 'status' => StatusRevisi::Disetujui, 'komentar' => 'Disetujui, sesuaikan SPT terkait.',
            ])->setRelation('item', $item);
            $this->kirim($pimpinan, fn ($r) => new RevisiJadwalDiajukan($r), $revisi, 45, false);
            $this->kirim($irban, fn ($r) => new RevisiJadwalDiputuskan($r), $revisi, 7200, true);
        }

        $this->kirim($pimpinan, fn ($h) => new RingkasanMingguan($h), app(Hitung::class)->hitung(CarbonImmutable::now('Asia/Jakarta')->startOfWeek()), 360, false);
    }

    /** Sends one database notification $menit minutes in the past; returns false when the source model is missing. */
    private function kirim(User $user, callable $buat, mixed $sumber, int $menit, bool $dibaca): bool
    {
        if (! $sumber) {
            return false;
        }
        /** @var NotifikasiSimon $n */
        $n = $buat($sumber);
        $n->id = (string) Str::uuid(); // the sender clones the notification, so fix the id here to find the row again
        Notification::sendNow($user, $n, ['database']);
        $waktu = now()->subMinutes($menit);
        $user->notifications()->where('id', $n->id)->update(['created_at' => $waktu, 'updated_at' => $waktu, 'read_at' => $dibaca ? $waktu->copy()->addMinutes(5) : null]);

        return true;
    }
}
