<?php

namespace App\Actions\Komentar;

use App\Actions\Komentar\Concerns\ValidasiKomentar;
use App\Enums\StatusPermintaan;
use App\Models\Komentar;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\KomentarBaru;
use App\Services\PenerimaNotifikasi;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pimpinan starts a thread, optionally as a Minta Penjelasan. Comments never change SPT data.
 *
 * handle(User, Spt, array{isi, minta_penjelasan}): Komentar
 */
class TulisKomentar
{
    use ValidasiKomentar;

    public function handle(User $user, Spt $spt, array $data): Komentar
    {
        Gate::forUser($user)->authorize('komentari', $spt);
        $e = [];
        $isi = $this->isi($data, $e);
        if ($e) {
            throw ValidationException::withMessages($e);
        }
        $minta = filter_var($data['minta_penjelasan'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $komentar = $spt->komentar()->create([
            'user_id' => $user->id, 'isi' => $isi, 'minta_penjelasan' => $minta,
            'status_permintaan' => $minta ? StatusPermintaan::Terbuka : null,
        ]);
        Notification::send(app(PenerimaNotifikasi::class)->utasKomentar($komentar), new KomentarBaru($komentar));

        return $komentar;
    }
}
