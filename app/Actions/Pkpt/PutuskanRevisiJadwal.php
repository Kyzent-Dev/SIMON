<?php

namespace App\Actions\Pkpt;

use App\Enums\StatusRevisi;
use App\Models\PkptItemRevisi;
use App\Models\User;
use App\Notifications\RevisiJadwalDiputuskan;
use App\Services\PenerimaNotifikasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class PutuskanRevisiJadwal
{
    /** Setujui (optional komentar) or Tolak (komentar required). Only a Menunggu revision can be decided. */
    public function handle(User $user, PkptItemRevisi $revisi, bool $setujui, ?string $komentar): PkptItemRevisi
    {
        Gate::forUser($user)->authorize('decide', $revisi);
        $komentar = trim((string) $komentar);

        $locked = DB::transaction(function () use ($user, $revisi, $setujui, $komentar) {
            $locked = PkptItemRevisi::whereKey($revisi->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== StatusRevisi::Menunggu) {
                throw ValidationException::withMessages(['revisi' => 'Revisi ini sudah diputuskan.']);
            }
            if (! $setujui && $komentar === '') {
                throw ValidationException::withMessages(['komentar' => 'Alasan penolakan wajib diisi.']);
            }
            if (mb_strlen($komentar) > 1000) {
                throw ValidationException::withMessages(['komentar' => 'Komentar maksimal 1000 karakter.']);
            }
            $locked->forceFill([
                'status' => $setujui ? StatusRevisi::Disetujui : StatusRevisi::Ditolak,
                'diputuskan_oleh' => $user->id, 'diputuskan_pada' => now(),
                'komentar' => $komentar === '' ? null : $komentar,
            ])->save();

            return $locked;
        });

        Notification::send(app(PenerimaNotifikasi::class)->irban($locked->item->irban_id), new RevisiJadwalDiputuskan($locked));

        return $locked;
    }
}
