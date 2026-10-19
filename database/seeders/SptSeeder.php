<?php

namespace Database\Seeders;

use App\Enums\StatusPengajuan;
use App\Models\Irban;
use App\Models\Klasifikasi;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Services\Penomoran;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Dev only. Run after IrbanSeeder and UserSeeder. Numbered SPT take their numbers through Penomoran.
class SptSeeder extends Seeder
{
    public function run(): void
    {
        $irban = Irban::where('nama', 'Irban I')->first();
        $user = User::where('username', 'irban1')->first();
        if (app()->isProduction() || ! $irban || ! $user || Spt::where('irban_id', $irban->id)->exists()) {
            return;
        }

        $tahun = now('Asia/Jakarta')->year;
        $khusus = Klasifikasi::where('kode', 'KHUSUS')->first();
        $penginput = Pegawai::orderBy('id')->value('id');
        $penomoran = app(Penomoran::class);

        $buat = function (StatusPengajuan $status, string $uraian, int $bulan, bool $nomor = false) use ($irban, $user, $khusus, $tahun, $penginput, $penomoran) {
            $mulai = CarbonImmutable::create($tahun, $bulan, 5);
            DB::transaction(function () use ($status, $uraian, $mulai, $nomor, $irban, $user, $khusus, $penginput, $penomoran) {
                $spt = (new Spt)->forceFill([
                    'irban_id' => $irban->id, 'klasifikasi_id' => $khusus->id, 'uraian_untuk' => $uraian, 'periode_diperiksa' => 'Tahun Anggaran '.($mulai->year - 1),
                    'tanggal_mulai' => $mulai, 'tanggal_selesai' => $mulai->addDays(9), 'status_pengajuan' => $status, 'dibuat_oleh' => $user->id,
                    'diinput_oleh' => $penginput, 'diajukan_pada' => $status === StatusPengajuan::Draf ? null : now(),
                ]);
                if ($nomor) {
                    $n = $penomoran->ambil($irban, $mulai, $khusus);
                    $spt->forceFill(['nomor_urut' => $n['nomor_urut'], 'nomor_lengkap' => $n['nomor_lengkap'], 'nomor_counter_id' => $n['counter']->id,
                        'tahun' => $mulai->year, 'tanggal_spt' => $mulai]);
                }
                $spt->save();
            });
        };

        $buat(StatusPengajuan::Draf, 'Pemeriksaan khusus pengelolaan dana desa (draf).', 11);
        foreach ([1, 2, 3] as $i) {
            $buat(StatusPengajuan::MenungguNomor, "Pemeriksaan khusus menunggu nomor #{$i}.", 10 + ($i % 3));
        }
        $buat(StatusPengajuan::PerluRevisi, 'Pemeriksaan khusus yang perlu direvisi.', 9);
        $buat(StatusPengajuan::Diterima, 'Pemeriksaan khusus yang sudah bernomor.', 3, true);
        $buat(StatusPengajuan::Diterima, 'Pemeriksaan khusus kedua yang sudah bernomor.', 4, true);
        $buat(StatusPengajuan::Dibatalkan, 'Pemeriksaan khusus yang nomornya dibatalkan.', 5, true);
    }
}
