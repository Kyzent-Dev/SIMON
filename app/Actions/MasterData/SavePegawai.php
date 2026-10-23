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
            'email' => ['nullable', 'string', 'max:255', 'email:rfc', Rule::unique('pegawai', 'email')->ignore($ignoreId)],
            'aktif' => ['boolean'],
        ];
    }

    public static function messages(): array
    {
        return [
            'nip.required' => 'NIP harus 18 digit angka.', 'nip.digits' => 'NIP harus 18 digit angka.', 'nip.unique' => 'NIP sudah terdaftar.',
            'email.email' => 'Format email tidak valid.', 'email.unique' => 'Email sudah dipakai pegawai lain.',
        ];
    }

    public function handle(?Pegawai $pegawai, array $data): Pegawai
    {
        $pegawai ??= new Pegawai;
        $t = fn ($v) => trim((string) $v) ?: null;
        try {
            $pegawai->fill([
                'nama_bergelar' => trim($data['nama']), 'nip' => trim($data['nip']),
                'pangkat' => $t($data['pangkat'] ?? null), 'golongan' => $t($data['golongan'] ?? null),
                'jabatan' => $t($data['jabatan'] ?? null), 'email' => ($e = $t($data['email'] ?? null)) ? mb_strtolower($e) : null, 'aktif' => $data['aktif'],
            ])->save();
        } catch (UniqueConstraintViolationException) {
            // Which unique column collided: the NIP is checked first, so anything else is the email.
            $nipDipakai = Pegawai::where('nip', trim($data['nip']))->when($pegawai->exists, fn ($q) => $q->whereKeyNot($pegawai->id))->exists();
            throw ValidationException::withMessages($nipDipakai ? ['nip' => self::messages()['nip.unique']] : ['email' => self::messages()['email.unique']]);
        }

        return $pegawai;
    }
}
