<?php

namespace App\Actions\MasterData;

use App\Models\Pegawai;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavePegawai
{
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'digits:18', Rule::unique('pegawai', 'nip')->ignore($ignoreId)],
            'pangkat' => ['nullable', 'string', 'max:100'],
            'golongan' => ['nullable', 'string', 'max:50'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'aktif' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return ['nip.required' => 'NIP harus 18 digit angka.', 'nip.digits' => 'NIP harus 18 digit angka.', 'nip.unique' => 'NIP sudah terdaftar.'];
    }

    public function handle(?Pegawai $pegawai, array $data): Pegawai
    {
        $pegawai ??= new Pegawai;
        $t = fn ($v) => trim((string) $v) ?: null;
        try {
            $pegawai->fill([
                'nama_bergelar' => trim($data['nama']), 'nip' => trim($data['nip']),
                'pangkat' => $t($data['pangkat'] ?? null), 'golongan' => $t($data['golongan'] ?? null),
                'jabatan' => $t($data['jabatan'] ?? null), 'aktif' => $data['aktif'],
            ])->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nip' => self::messages()['nip.unique']]);
        }

        return $pegawai;
    }
}
