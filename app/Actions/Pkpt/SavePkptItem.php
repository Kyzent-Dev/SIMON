<?php

namespace App\Actions\Pkpt;

use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SavePkptItem
{
    /** Item rules; returns normalized attributes or throws ValidationException. Reusable by the import (Task 3). */
    public static function validated(Pkpt $pkpt, array $data): array
    {
        $errors = [];
        $irban = $data['irban_id'] ?? null;
        $jenis = $data['jenis_penugasan_id'] ?? null;
        if (! $irban || ! Irban::whereKey($irban)->exists()) {
            $errors['irban_id'] = 'Pilih irban.';
        }
        if (! $jenis || ! JenisPenugasan::whereKey($jenis)->exists()) {
            $errors['jenis_penugasan_id'] = 'Pilih jenis penugasan.';
        }

        $dates = [];
        foreach (['tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai'] as $f => $label) {
            try {
                $dates[$f] = CarbonImmutable::parse((string) ($data[$f] ?? ''))->startOfDay();
            } catch (\Throwable) {
                $errors[$f] = "{$label} wajib diisi.";
            }
        }
        if (count($dates) === 2) {
            if ($dates['tanggal_mulai']->gt($dates['tanggal_selesai'])) {
                $errors['tanggal_selesai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
            }
            foreach ($dates as $f => $d) {
                if ($d->year !== (int) $pkpt->tahun) {
                    $errors[$f] = "Tanggal harus berada di tahun {$pkpt->tahun}.";
                }
            }
        }

        $ket = trim((string) ($data['keterangan'] ?? ''));
        if (mb_strlen($ket) > 255) {
            $errors['keterangan'] = 'Keterangan maksimal 255 karakter.';
        }
        $target = $data['target_spt'] ?? null;
        if ($target === '' || $target === null) {
            $target = null;
        } elseif (($target = filter_var($target, FILTER_VALIDATE_INT)) === false || $target < 1 || $target > 999) {
            $errors['target_spt'] = 'Target SPT harus berupa angka 1 sampai 999.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'irban_id' => (int) $irban, 'jenis_penugasan_id' => (int) $jenis,
            'tanggal_mulai' => $dates['tanggal_mulai'], 'tanggal_selesai' => $dates['tanggal_selesai'],
            'keterangan' => $ket === '' ? null : $ket, 'target_spt' => $target,
        ];
    }

    public function handle(Pkpt $pkpt, ?PkptItem $item, array $data): PkptItem
    {
        SavePkpt::ensureDraf($pkpt);
        if ($item?->exists && $item->pkpt_id !== $pkpt->id) {
            throw ValidationException::withMessages(['item' => 'Item bukan bagian dari PKPT ini.']);
        }
        $attrs = self::validated($pkpt, $data);
        $item ??= new PkptItem;
        $item->fill(['pkpt_id' => $pkpt->id] + $attrs)->save();

        return $item;
    }
}
