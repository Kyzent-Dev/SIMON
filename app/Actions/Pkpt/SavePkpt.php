<?php

namespace App\Actions\Pkpt;

use App\Models\Pkpt;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

class SavePkpt
{
    public static function ensureDraf(Pkpt $pkpt): void
    {
        if (! $pkpt->isDraf()) {
            throw ValidationException::withMessages(['pkpt' => 'PKPT yang sudah disahkan tidak dapat diubah.']);
        }
    }

    /** @param array{tahun: mixed, nomor_sk?: ?string, tanggal_sk?: mixed} $data */
    public function handle(?Pkpt $pkpt, array $data): Pkpt
    {
        $pkpt ??= new Pkpt;
        if ($pkpt->exists) {
            self::ensureDraf($pkpt);
        }

        $max = (int) now()->year + 1;
        $tahun = filter_var($data['tahun'] ?? null, FILTER_VALIDATE_INT);
        if ($tahun === false || $tahun < 2000 || $tahun > $max) {
            throw ValidationException::withMessages(['tahun' => "Tahun harus antara 2000 dan {$max}."]);
        }
        if ($pkpt->exists && $tahun !== (int) $pkpt->tahun && $pkpt->items()->exists()) {
            throw ValidationException::withMessages(['tahun' => 'Tahun tidak dapat diubah selama PKPT masih memiliki item.']);
        }
        $duplicate = ['tahun' => "PKPT tahun {$tahun} sudah ada."];
        if (Pkpt::where('tahun', $tahun)->where('id', '!=', $pkpt->id ?? 0)->exists()) {
            throw ValidationException::withMessages($duplicate);
        }

        $nomor = trim((string) ($data['nomor_sk'] ?? ''));
        if (mb_strlen($nomor) > 255) {
            throw ValidationException::withMessages(['nomor_sk' => 'Nomor SK maksimal 255 karakter.']);
        }
        $tanggal = null;
        if (filled($data['tanggal_sk'] ?? null)) {
            try {
                $tanggal = CarbonImmutable::parse($data['tanggal_sk'])->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages(['tanggal_sk' => 'Tanggal SK tidak valid.']);
            }
        }

        try {
            $pkpt->fill(['tahun' => $tahun, 'nomor_sk' => $nomor === '' ? null : $nomor, 'tanggal_sk' => $tanggal])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages($duplicate);
        }

        return $pkpt;
    }
}
