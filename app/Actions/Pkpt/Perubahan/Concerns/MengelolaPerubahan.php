<?php

namespace App\Actions\Pkpt\Perubahan\Concerns;

use App\Enums\JenisPerubahan;
use App\Enums\StatusPerubahan;
use App\Models\PkptPerubahan;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Shared guards of the Perubahan PKPT actions: re-read under lock, editable states, riwayat. */
trait MengelolaPerubahan
{
    /** Stale-tab message (key `perubahan`, shown as a toast). */
    public const BASI = 'Perubahan ini sudah diajukan atau diputuskan.';

    /** Locks the package (call inside a transaction) and requires draf or ditolak. */
    private function kunciTerbuka(PkptPerubahan $p): PkptPerubahan
    {
        $p = PkptPerubahan::whereKey($p->id)->lockForUpdate()->first();
        if (! in_array($p?->status, [StatusPerubahan::Draf, StatusPerubahan::Ditolak], true)) {
            throw ValidationException::withMessages(['perubahan' => self::BASI]);
        }

        return $p;
    }

    private function catat(PkptPerubahan $p, string $kejadian, ?User $oleh, ?string $catatan = null): void
    {
        $p->riwayat()->create(['kejadian' => $kejadian, 'oleh' => $oleh?->id, 'catatan' => $catatan]);
    }

    /** @return array{jenis: string, alasan: string} */
    private function isiPaket(array $data): array
    {
        $e = [];
        $jenis = JenisPerubahan::tryFrom((string) ($data['jenis'] ?? ''));
        if (! $jenis) {
            $e['jenis'] = 'Pilih jenis perubahan.';
        }
        $alasan = trim((string) ($data['alasan'] ?? ''));
        if ($alasan === '') {
            $e['alasan'] = 'Alasan wajib diisi.';
        } elseif (mb_strlen($alasan) > 2000) {
            $e['alasan'] = 'Alasan maksimal 2000 karakter.';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        return ['jenis' => $jenis->value, 'alasan' => $alasan];
    }
}
