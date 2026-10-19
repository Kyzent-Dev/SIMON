<?php

namespace App\Actions\Spt;

use App\Actions\Spt\Concerns\MelaksanakanSpt;
use App\Enums\KejadianSpt;
use App\Models\Spt;
use App\Models\SptDokumen;
use App\Models\User;
use App\Services\Settings;
use App\Support\BerkasSpt;
use App\Support\DiinputOleh;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Irban uploads the signed final SPT. Earlier documents are never deleted: the latest row of a jenis is the current one.
 *
 * handle(User, Spt, array{diinput_oleh}, ?UploadedFile $berkas, string $jenis = 'final'): SptDokumen
 * Errors: `berkas` (missing, not PDF, over upload.maks_mb), `diinput_oleh`, `spt` (already Selesai).
 */
class UnggahDokumenSpt
{
    use MelaksanakanSpt;

    public function __construct(private Settings $settings) {}

    public function handle(User $user, Spt $spt, array $data, ?UploadedFile $berkas, string $jenis = 'final'): SptDokumen
    {
        if ($jenis !== 'final') {
            throw new \InvalidArgumentException("Jenis dokumen {$jenis} tidak didukung.");
        }
        $this->izinkan($user, $spt);
        $e = [];
        $diinput = $this->penginput($user, $data, $e);
        $maks = (int) $this->settings->get('upload.maks_mb');
        if (! $berkas) {
            $e['berkas'] = 'Pilih berkas PDF.';
        } elseif (! BerkasSpt::pdf($berkas, $maks * 1024 * 1024)) {
            $e['berkas'] = "Dokumen harus PDF dan maksimal {$maks} MB.";
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        $path = null;
        try {
            $dokumen = DB::transaction(function () use ($user, $spt, $berkas, $jenis, $diinput, &$path) {
                $spt = $this->kunci($user, $spt);
                $dokumen = BerkasSpt::simpan($spt, $berkas, $jenis, $user, $diinput);
                $path = $dokumen->path;
                $spt->riwayat()->create([
                    'kejadian' => KejadianSpt::FINAL_DIUNGGAH, 'nilai_baru' => ['nama_asli' => $dokumen->nama_asli, 'sha256' => $dokumen->sha256],
                    'oleh' => $user->id, 'diinput_oleh' => $diinput,
                ]);

                return $dokumen;
            });
        } catch (\Throwable $ex) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $ex;
        }
        DiinputOleh::ingat($user, $diinput);

        return $dokumen;
    }
}
