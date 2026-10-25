<?php

namespace App\Actions\Spt;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Enums\SumberSpt;
use App\Models\Aktivitas;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Writes legacy SPT analyzed by SptImportParser (rows valid or warning) in one transaction. Imported SPT keep their old number
 * (no counter), and the notification idempotence columns are filled so neither the import nor `notifikasi:harian` notifies.
 */
class ImporSpt
{
    public function handle(User $admin, array $rows, string $berkas): int
    {
        Gate::forUser($admin)->authorize('manage-master-data');
        $rows = array_values(array_filter($rows, fn ($r) => in_array($r['status'] ?? null, ['valid', 'warning'], true)));
        $nomor = null;

        try {
            $n = DB::transaction(function () use ($admin, $rows, $berkas, &$nomor) {
                foreach ($rows as $r) {
                    $d = $r['data'];
                    $nomor = $d['nomor_lengkap'];
                    $this->simpan($admin, $d, $berkas);
                }

                return count($rows);
            });
        } catch (UniqueConstraintViolationException) {
            // The number was taken between preview and import; the transaction rolled everything back.
            throw ValidationException::withMessages(['impor' => "Nomor SPT {$nomor} sudah dipakai. Muat ulang pratinjau."]);
        }
        if ($n > 0) {
            Aktivitas::catatImpor($admin, 'spt', $berkas, $n);
        }

        return $n;
    }

    private function simpan(User $admin, array $d, string $berkas): void
    {
        $spt = new Spt(array_intersect_key($d, array_flip(['irban_id', 'klasifikasi_id', 'pkpt_item_id', 'jenis_penugasan_id', 'ppj_pegawai_id',
            'periode_diperiksa', 'uraian_untuk', 'tanggal_mulai', 'tanggal_selesai'])));
        $spt->forceFill([
            'status_pengajuan' => StatusPengajuan::Diterima, 'sumber' => SumberSpt::Impor, 'dibuat_oleh' => $admin->id, 'diinput_oleh' => null,
            'diajukan_pada' => $d['tanggal_spt'].' 00:00:00', 'tahun' => $d['tahun'], 'tanggal_spt' => $d['tanggal_spt'],
            'nomor_lengkap' => $d['nomor_lengkap'], 'nomor_urut' => $d['nomor_urut'], 'nomor_counter_id' => null,
            'nomor_lhp' => $d['nomor_lhp'], 'tanggal_lhp' => $d['tanggal_lhp'],
            'ditandai_selesai_pada' => $d['selesai'] ? ($d['tanggal_lhp'] ?? $d['tanggal_selesai']).' 00:00:00' : null,
            'ditandai_selesai_oleh' => $d['selesai'] ? $admin->id : null,
            'terlambat_diberitahu_pada' => now(), 'pengingat_final_pada' => now(),
        ])->save();

        // Final-review ruling: only the Spt `created` row is logged per SPT; child rows would multiply the log ~6x on a 2000-row file.
        activity()->withoutLogs(function () use ($spt, $admin, $d, $berkas) {
            $spt->objek()->attach($d['objek_ids']);
            foreach ($d['personel'] as $i => $p) {
                $spt->personel()->create(['pegawai_id' => $p['pegawai_id'], 'peran' => $p['peran'], 'urutan' => $i + 1, 'sumber' => 'tambahan', 'tim_id' => null]);
            }
            if ($d['kendala_kategori'] !== null) {
                $spt->kendala()->create(['kategori' => $d['kendala_kategori'], 'uraian' => $d['kendala_uraian'], 'diinput_oleh' => null, 'dibuat_oleh' => $admin->id]);
            }
            $spt->riwayat()->create(['kejadian' => KejadianSpt::DIIMPOR, 'nilai_baru' => ['nomor_lengkap' => $d['nomor_lengkap'], 'berkas' => $berkas], 'oleh' => $admin->id]);
        });
    }
}
