<?php

namespace App\Actions\MasterData;

use App\Models\Irban;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveIrban
{
    public static function rules(?int $ignoreId = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('irban', 'nama')->ignore($ignoreId)],
            'kodeNomor' => ['required', 'string', 'max:100', Rule::unique('irban', 'kode_nomor')->ignore($ignoreId)],
            'emailNotifikasi' => ['nullable', 'email', 'max:255'],
            'tembusan' => ['array'],
            'tembusan.*' => ['email', 'max:255', 'distinct:ignore_case'],
        ];
    }

    public static function messages(): array
    {
        return [
            'nama.unique' => 'Nama Irban sudah dipakai.',
            'kodeNomor.unique' => 'Kode nomor sudah dipakai.',
            'emailNotifikasi.email' => 'Format email tidak valid.',
            'tembusan.*.email' => 'Format email tidak valid.',
            'tembusan.*.distinct' => 'Email tembusan tidak boleh sama.',
        ];
    }

    public function handle(?Irban $irban, array $data): Irban
    {
        $irban ??= new Irban;
        try {
            $irban->fill([
                'nama' => $data['nama'], 'kode_nomor' => $data['kodeNomor'],
                'email_notifikasi' => $data['emailNotifikasi'] ?: null,
                // An empty list is stored as null.
                'email_tembusan' => $data['tembusan'] ? array_values($data['tembusan']) : null,
            ])->save();
        } catch (UniqueConstraintViolationException $e) {
            // The driver message names the violated column.
            $field = str_contains(Str::before($e->getMessage(), 'SQL:'), 'kode_nomor') ? 'kodeNomor' : 'nama';
            throw ValidationException::withMessages([$field => self::messages()["$field.unique"]]);
        }

        return $irban;
    }
}
