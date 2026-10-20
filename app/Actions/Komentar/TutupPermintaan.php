<?php

namespace App\Actions\Komentar;

use App\Enums\Role;
use App\Enums\StatusPermintaan;
use App\Models\Komentar;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Any pimpinan closes a Minta Penjelasan once an Irban has replied (PRD §6.3). bisa() is the one rule, read by the UI and by handle().
 *
 * handle(User, Spt, array{komentar_id}): Komentar
 */
class TutupPermintaan
{
    /** Blocking message, or null when the root can be closed. */
    public function bisa(Komentar $root): ?string
    {
        if (! $root->minta_penjelasan || $root->parent_id !== null) {
            return 'Komentar ini bukan permintaan penjelasan.';
        }
        if ($root->status_permintaan === StatusPermintaan::Selesai) {
            return 'Permintaan penjelasan sudah ditutup.';
        }
        if (! $root->balasan()->whereHas('penulis', fn ($q) => $q->where('role', Role::Irban))->exists()) {
            return 'Permintaan baru dapat ditutup setelah Irban membalas.';
        }

        return null;
    }

    public function handle(User $user, Spt $spt, array $data): Komentar
    {
        Gate::forUser($user)->authorize('tutupPermintaan', $spt);

        return DB::transaction(function () use ($user, $spt, $data) {
            $root = $spt->komentar()->whereKey(filter_var($data['komentar_id'] ?? null, FILTER_VALIDATE_INT) ?: 0)->lockForUpdate()->first();
            if (! $root) {
                throw ValidationException::withMessages(['komentar' => 'Permintaan penjelasan tidak ditemukan.']);
            }
            if ($galat = $this->bisa($root)) {
                throw ValidationException::withMessages(['komentar' => $galat]);
            }
            $root->forceFill(['status_permintaan' => StatusPermintaan::Selesai, 'ditutup_oleh' => $user->id, 'ditutup_pada' => now()])->save();

            return $root;
        });
    }
}
