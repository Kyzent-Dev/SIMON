<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPermintaan;
use App\Models\Komentar;
use App\Models\Spt;
use App\Models\User;
use App\Support\DiinputOleh;
use Illuminate\Database\Seeder;

// Dev only. Run after SptSeeder, before NotifikasiSeeder. Rows are inserted directly (no notifications).
class KomentarSeeder extends Seeder
{
    public function run(): void
    {
        $pimpinan = User::where('username', 'pimpinan')->first();
        $irban = User::where('username', 'irban1')->first();
        $umum = User::where('username', 'bagianumum')->first();
        $penginput = $irban ? DiinputOleh::pilihan($irban)->first() : null;
        if (app()->isProduction() || ! $pimpinan || ! $irban?->irban_id || ! $umum || ! $penginput) {
            return;
        }

        $spt = Spt::where('irban_id', $irban->irban_id)->where('status_pengajuan', StatusPengajuan::Diterima)->whereNotNull('nomor_lengkap')->orderBy('id')->limit(2)->get();
        if ($spt->count() < 2 || Komentar::whereIn('spt_id', $spt->modelKeys())->exists()) {
            return;
        }

        $tulis = function (Spt $s, User $u, string $isi, string $waktu, array $lain = []) use ($penginput): Komentar {
            $k = (new Komentar)->forceFill(['spt_id' => $s->id, 'user_id' => $u->id, 'isi' => $isi,
                'diinput_oleh' => $u->role === Role::Irban ? $penginput->id : null] + $lain);
            $k->created_at = $k->updated_at = now()->modify($waktu);
            $k->save();

            return $k;
        };

        // Thread biasa: komentar pimpinan, balasan Irban, balasan Bagian Umum.
        $biasa = $tulis($spt[0], $pimpinan, 'Mohon pastikan daftar personel sesuai dengan surat tugas yang ditandatangani.', '-3 days');
        $tulis($spt[0], $irban, 'Baik, daftar personel sudah kami cek dan sesuai.', '-3 days +4 hours', ['parent_id' => $biasa->id]);
        $tulis($spt[0], $umum, 'Nomor SPT sudah tercatat di register.', '-2 days', ['parent_id' => $biasa->id]);

        // Minta Penjelasan Terbuka, belum dibalas Irban.
        $tulis($spt[0], $pimpinan, "Jelaskan dasar perubahan periode yang diperiksa dibanding rencana PKPT.\nMohon dijawab sebelum akhir pekan.", '-5 hours', [
            'minta_penjelasan' => true, 'status_permintaan' => StatusPermintaan::Terbuka]);

        // Minta Penjelasan Selesai: Irban membalas, pimpinan menutup.
        $selesai = $tulis($spt[1], $pimpinan, 'Mengapa jumlah hari pemeriksaan melebihi standar?', '-6 days', [
            'minta_penjelasan' => true, 'status_permintaan' => StatusPermintaan::Selesai]);
        $tulis($spt[1], $irban, 'Objek pemeriksaan tersebar di tiga kecamatan sehingga perlu tambahan hari kerja.', '-5 days -20 hours', ['parent_id' => $selesai->id]);
        $selesai->forceFill(['ditutup_oleh' => $pimpinan->id, 'ditutup_pada' => now()->modify('-5 days -2 hours')])->save();
    }
}
