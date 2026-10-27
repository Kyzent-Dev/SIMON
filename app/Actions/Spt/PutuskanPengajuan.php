<?php

namespace App\Actions\Spt;

use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\EmailPegawai;
use App\Notifications\PengajuanDiputuskan;
use App\Services\PenerimaNotifikasi;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/** Shared core of the Bagian Umum decisions: lock the SPT, require menunggu_nomor (else the conflict message), authorize, apply, write riwayat. */
trait PutuskanPengajuan
{
    public const KONFLIK = 'Pengajuan ini sudah diproses oleh pengguna lain.';

    /** @param Closure(Spt): array{0: StatusPengajuan, 1: string, 2: ?string, 3?: array} $ubah returns [status, kejadian, alasan, extra nilai_baru] after forceFilling the locked SPT */
    private function putuskan(User $user, Spt $spt, Closure $ubah): Spt
    {
        Gate::forUser($user)->authorize('kelolaNomor', Spt::class);

        [$spt, $alasan] = DB::transaction(function () use ($user, $spt, $ubah) {
            $spt = Spt::whereKey($spt->id)->lockForUpdate()->first();
            if ($spt?->status_pengajuan !== StatusPengajuan::MenungguNomor) {
                throw ValidationException::withMessages(['spt' => self::KONFLIK]);
            }
            Gate::forUser($user)->authorize('decide', $spt);

            [$status, $kejadian, $alasan, $extra] = $ubah($spt) + [3 => []];
            $spt->forceFill(['status_pengajuan' => $status])->save();
            $spt->riwayat()->create([
                'kejadian' => $kejadian, 'nilai_lama' => ['status_pengajuan' => StatusPengajuan::MenungguNomor->value],
                'nilai_baru' => ['status_pengajuan' => $status->value] + $extra, 'alasan' => $alasan, 'oleh' => $user->id,
            ]);

            return [$spt, $alasan];
        }, attempts: 3);

        $penerima = app(PenerimaNotifikasi::class);
        $akun = $penerima->irban($spt->irban_id);
        Notification::send($akun, new PengajuanDiputuskan($spt, $alasan));
        if ($email = $penerima->emailPegawai($spt->diinputOleh, $akun)) {
            Notification::route('mail', $email)->notify(new EmailPegawai(new PengajuanDiputuskan($spt, $alasan), $spt->diinputOleh->nama_bergelar));
        }

        return $spt;
    }

    /** Authorizes first, so a wrong role gets 403 whatever it sends as the catatan. */
    private function catatan(User $user, string $catatan): string
    {
        Gate::forUser($user)->authorize('kelolaNomor', Spt::class);
        $catatan = trim($catatan);
        if (mb_strlen($catatan) < 10) {
            throw ValidationException::withMessages(['catatan' => 'Catatan minimal 10 karakter.']);
        }
        if (mb_strlen($catatan) > 1000) {
            throw ValidationException::withMessages(['catatan' => 'Catatan maksimal 1000 karakter.']);
        }

        return $catatan;
    }
}
