<?php

namespace App\Actions\Komentar;

use App\Actions\Komentar\Concerns\ValidasiKomentar;
use App\Enums\Role;
use App\Models\Komentar;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\BalasanPenjelasan;
use App\Notifications\KomentarBaru;
use App\Services\PenerimaNotifikasi;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Pimpinan, Bagian Umum or the owning Irban replies in a thread (one level: the parent must be a root of this SPT).
 * A reply on a closed permintaan is allowed. An Irban reply needs "Diinput oleh".
 *
 * handle(User, Spt, array{parent_id, isi, diinput_oleh}): Komentar
 */
class BalasKomentar
{
    use ValidasiKomentar;

    public function handle(User $user, Spt $spt, array $data): Komentar
    {
        Gate::forUser($user)->authorize('balasKomentar', $spt);
        $e = [];
        $isi = $this->isi($data, $e);
        $irban = $user->role === Role::Irban;
        $diinput = null;
        if ($irban) {
            $diinput = filter_var($data['diinput_oleh'] ?? null, FILTER_VALIDATE_INT) ?: null;
            if ($galat = DiinputOleh::galat($user, $diinput)) {
                $e['diinput_oleh'] = $galat;
            }
        }
        $induk = $spt->komentar()->whereNull('parent_id')->whereKey(filter_var($data['parent_id'] ?? null, FILTER_VALIDATE_INT) ?: 0)->first();
        if (! $induk) {
            $e['komentar'] = 'Komentar yang dibalas tidak ditemukan.';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        $balasan = $spt->komentar()->create(['parent_id' => $induk->id, 'user_id' => $user->id, 'diinput_oleh' => $diinput, 'isi' => $isi]);
        if ($irban) {
            DiinputOleh::ingat($user, $diinput);
        }

        $penerima = app(PenerimaNotifikasi::class)->utasKomentar($balasan);
        if ($induk->minta_penjelasan && $induk->user_id !== $user->id) {
            Notification::send($penerima->where('id', $induk->user_id), new BalasanPenjelasan($balasan));
            $penerima = $penerima->reject(fn (User $u) => $u->id === $induk->user_id);
        }
        Notification::send($penerima, new KomentarBaru($balasan));

        return $balasan;
    }
}
