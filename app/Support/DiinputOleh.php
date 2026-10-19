<?php

namespace App\Support;

use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Collection;

/** "Diinput oleh" (login.md §5): who on a shared Irban account really did the write. One source for the choices, the rule and the session prefill. */
class DiinputOleh
{
    public static function tahun(): int
    {
        return now('Asia/Jakarta')->year;
    }

    /** Active PPJ + anggota of the user's irban for the current year, sorted by name. @return Collection<int, Pegawai> */
    public static function pilihan(User $user): Collection
    {
        $komposisi = $user->irban_id
            ? IrbanTahunan::with(['ppj', 'anggota'])->where('irban_id', $user->irban_id)->where('tahun', self::tahun())->first()
            : null;
        if (! $komposisi) {
            return collect();
        }

        return collect([$komposisi->ppj])->concat($komposisi->anggota)->filter(fn (?Pegawai $p) => $p?->aktif)
            ->unique('id')->sortBy('nama_bergelar')->values();
    }

    /** Error message for a submitted "Diinput oleh" id (null = valid). */
    public static function galat(User $user, ?int $id): ?string
    {
        if (! $id) {
            return 'Pilih nama penginput terlebih dahulu';
        }

        return self::pilihan($user)->contains('id', $id) ? null : 'Penginput harus PPJ atau anggota aktif Irban ini tahun '.self::tahun().'.';
    }

    /** "Budi Santoso · …0012" */
    public static function label(Pegawai $p): string
    {
        return $p->nama_bergelar.' · …'.substr($p->nip, -4);
    }

    /** Called only after a successful write. */
    public static function ingat(User $user, int $pegawaiId): void
    {
        session()->put("diinput_oleh.{$user->irban_id}", $pegawaiId);
    }

    /** Last choice of this session, only while it is still a valid choice. */
    public static function terakhir(User $user): ?int
    {
        $id = session("diinput_oleh.{$user->irban_id}");

        return $id && self::pilihan($user)->contains('id', $id) ? (int) $id : null;
    }
}
