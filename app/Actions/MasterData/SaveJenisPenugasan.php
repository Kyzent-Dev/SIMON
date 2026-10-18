<?php

namespace App\Actions\MasterData;

use App\Models\JenisPenugasan;
use App\Services\ColorRules;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveJenisPenugasan
{
    public static function rules(?int $ignoreId = null): array
    {
        return ['nama' => ['required', 'string', 'max:255', Rule::unique('jenis_penugasan', 'nama')->ignore($ignoreId)]];
    }

    public static function messages(): array
    {
        return ['nama.required' => 'Nama jenis penugasan wajib diisi.', 'nama.unique' => 'Nama jenis penugasan sudah dipakai.'];
    }

    /** Resolve the picker choice ('1'..'12' or 'custom' + hex) to [warna, warna_teks]; throws on a bad custom color. */
    public static function resolveColor(string $swatch, string $custom): array
    {
        foreach (ColorRules::SWATCHES as $s) {
            if ((string) $s['id'] === $swatch) {
                return [$s['hex'], $s['label']];
            }
        }
        $errors = $swatch === 'custom' ? ColorRules::validateCustom($custom) : ['Pilih warna.'];
        if ($errors) {
            throw ValidationException::withMessages(['custom' => $errors]);
        }
        $hex = ColorRules::normalize($custom);

        return [$hex, ColorRules::labelFor($hex)];
    }

    public function handle(?JenisPenugasan $jenis, string $nama, string $swatch, string $custom): JenisPenugasan
    {
        [$warna, $teks] = self::resolveColor($swatch, $custom);
        $jenis ??= new JenisPenugasan;
        try {
            $jenis->fill(['nama' => $nama, 'warna' => $warna, 'warna_teks' => $teks])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nama' => self::messages()['nama.unique']]);
        }

        return $jenis;
    }
}
