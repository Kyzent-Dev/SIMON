<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Models\PkptPerubahan;
use App\Models\User;
use App\Services\Settings;
use App\Support\BerkasSpt;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * SK of a package (any status, also after approval): nomor, tanggal, PDF on the private `local` disk at `perubahan-pkpt/{id}/{uuid}.pdf`.
 * Blank nomor/tanggal clear the field; no file keeps the current one. Every change is written to the riwayat.
 */
class SimpanSkPerubahan
{
    use MengelolaPerubahan;

    public function __construct(private Settings $settings) {}

    public function handle(User $user, PkptPerubahan $p, array $data, ?UploadedFile $berkas): PkptPerubahan
    {
        Gate::forUser($user)->authorize('kelolaSk', $p);
        $e = [];
        $nomor = trim((string) ($data['nomor_sk'] ?? ''));
        if (mb_strlen($nomor) > 255) {
            $e['nomor_sk'] = 'Nomor SK maksimal 255 karakter.';
        }
        $tanggal = null;
        if (filled($data['tanggal_sk'] ?? null)) {
            try {
                $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', (string) $data['tanggal_sk']) ?: null;
            } catch (\Throwable) {
            }
            if (! $tanggal || $tanggal->format('Y-m-d') !== (string) $data['tanggal_sk']) {
                $tanggal = null;
                $e['tanggal_sk'] = 'Tanggal SK tidak valid.';
            }
        }
        $maks = (int) $this->settings->get('upload.maks_mb');
        if ($berkas && ! BerkasSpt::pdf($berkas, $maks * 1024 * 1024)) {
            $e['berkas_sk'] = "Berkas SK harus PDF dan maksimal {$maks} MB.";
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        $path = $berkas?->storeAs("perubahan-pkpt/{$p->id}", Str::uuid().'.pdf', 'local');
        try {
            [$p, $lama] = DB::transaction(function () use ($user, $p, $nomor, $tanggal, $berkas, $path) {
                $p = PkptPerubahan::whereKey($p->id)->lockForUpdate()->firstOrFail();
                $lama = $p->berkas_sk;
                $p->fill(['nomor_sk' => $nomor === '' ? null : $nomor, 'tanggal_sk' => $tanggal?->format('Y-m-d')]);
                if ($path) {
                    $p->forceFill(['berkas_sk' => $path, 'hash_sk' => hash_file('sha256', $berkas->getRealPath())]);
                }
                $diubah = collect(['nomor_sk' => 'nomor SK', 'tanggal_sk' => 'tanggal SK', 'berkas_sk' => 'berkas SK'])
                    ->filter(fn ($label, $kolom) => $p->isDirty($kolom))->values();
                if ($diubah->isEmpty()) {
                    return [$p, null];
                }
                $p->save(); // sk_lengkap_pada follows skLengkap() (PkptPerubahan::booted)
                $this->catat($p, 'sk_diubah', $user, 'Diubah: '.$diubah->implode(', ').'.');

                return [$p, $path ? $lama : null];
            });
        } catch (\Throwable $ex) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
            throw $ex;
        }
        if ($lama) {
            Storage::disk('local')->delete($lama);
        }

        return $p;
    }
}
