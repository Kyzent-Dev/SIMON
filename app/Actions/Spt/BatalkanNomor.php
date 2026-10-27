<?php

namespace App\Actions\Spt;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\NomorDibatalkan;
use App\Services\PenerimaNotifikasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Cancels an issued number. Only the status changes: the number stays in the register and the counter never decrements (PRD §9.1). */
class BatalkanNomor
{
    public function handle(User $user, Spt $spt, string $alasan): Spt
    {
        Gate::forUser($user)->authorize('kelolaNomor', Spt::class);
        $alasan = trim($alasan);
        if (mb_strlen($alasan) < 10) {
            throw ValidationException::withMessages(['alasan' => 'Alasan pembatalan minimal 10 karakter.']);
        }
        if (mb_strlen($alasan) > 1000) {
            throw ValidationException::withMessages(['alasan' => 'Alasan pembatalan maksimal 1000 karakter.']);
        }

        $spt = DB::transaction(function () use ($user, $spt, $alasan) {
            $spt = Spt::whereKey($spt->id)->lockForUpdate()->firstOrFail();
            if ($spt->status_pengajuan !== StatusPengajuan::Diterima || $spt->nomor_urut === null) {
                throw ValidationException::withMessages(['spt' => 'Hanya nomor yang sudah terbit yang dapat dibatalkan.']);
            }
            $spt->forceFill(['status_pengajuan' => StatusPengajuan::Dibatalkan])->save();
            $spt->riwayat()->create([
                'kejadian' => KejadianSpt::DIBATALKAN, 'nilai_lama' => ['status_pengajuan' => StatusPengajuan::Diterima->value],
                'nilai_baru' => ['status_pengajuan' => StatusPengajuan::Dibatalkan->value, 'nomor_lengkap' => $spt->nomor_lengkap], 'alasan' => $alasan, 'oleh' => $user->id,
            ]);

            return $spt;
        });

        app(PenerimaNotifikasi::class)->kirimKeIrban($spt->irban_id, $spt->diinputOleh, fn () => new NomorDibatalkan($spt, $alasan));

        return $spt;
    }
}
