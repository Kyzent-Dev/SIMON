<?php

namespace App\Actions\Spt;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Nomor SPPD (K5), the single place that normalises (trim, empty = null), validates (max 100) and writes it with an SPT riwayat
 * (old and new value). Used by the SPT detail panel (handle) and, inside their own transactions, by TerimaPengajuan and CatatNomorManual (terapkan).
 * Duplicates in the same year only warn (duplikat), never block.
 */
class UbahNomorSppd
{
    public const DIBATALKAN = 'SPT yang sudah Dibatalkan tidak dapat diubah nomor SPPD-nya.';

    /** Trims and validates; error key `nomor_sppd`. */
    public static function normal(mixed $nomor): ?string
    {
        $nomor = is_string($nomor) ? (trim($nomor) === '' ? null : trim($nomor)) : $nomor;
        Validator::make(['nomor_sppd' => $nomor], ['nomor_sppd' => ['nullable', 'string', 'max:100']], [
            'nomor_sppd.max' => 'Nomor SPPD maksimal 100 karakter.', 'nomor_sppd.string' => 'Nomor SPPD tidak valid.',
        ])->validate();

        return $nomor;
    }

    /** Another SPT of the same year with the same number (case-insensitive after trim), or null. Non-throwing, for the UI warning. */
    public function duplikat(int $tahun, mixed $nomor, ?int $kecualiSptId = null): ?Spt
    {
        $nomor = is_string($nomor) ? trim($nomor) : '';
        if ($nomor === '' || mb_strlen($nomor) > 100) {
            return null;
        }

        return Spt::where('tahun', $tahun)->whereRaw('LOWER(nomor_sppd) = ?', [mb_strtolower($nomor)])
            ->when($kecualiSptId, fn ($q, $id) => $q->whereKeyNot($id))->orderBy('id')->first();
    }

    /** SPT detail panel: locks the SPT; a Dibatalkan SPT (cancelled in another tab) is a `spt` error for a toast. */
    public function handle(User $user, Spt $spt, mixed $nomor): Spt
    {
        Gate::forUser($user)->authorize('kelolaNomor', Spt::class);
        $nomor = self::normal($nomor);

        return DB::transaction(function () use ($user, $spt, $nomor) {
            $spt = Spt::whereKey($spt->id)->lockForUpdate()->firstOrFail();
            if ($spt->status_pengajuan === StatusPengajuan::Dibatalkan) {
                throw ValidationException::withMessages(['spt' => self::DIBATALKAN]);
            }
            $this->terapkan($user, $spt, $nomor);

            return $spt;
        });
    }

    /** Writes the number on an SPT the caller has locked in its transaction; no change = no riwayat. Returns whether it changed. */
    public function terapkan(User $user, Spt $spt, mixed $nomor): bool
    {
        Gate::forUser($user)->authorize('ubahSppd', $spt);
        $nomor = self::normal($nomor);
        $lama = $spt->nomor_sppd;
        if ($lama === $nomor) {
            return false;
        }
        $spt->forceFill(['nomor_sppd' => $nomor])->save();
        $spt->riwayat()->create([
            'kejadian' => KejadianSpt::SPPD_DIUBAH, 'nilai_lama' => ['nomor_sppd' => $lama], 'nilai_baru' => ['nomor_sppd' => $nomor], 'oleh' => $user->id,
        ]);

        return true;
    }
}
