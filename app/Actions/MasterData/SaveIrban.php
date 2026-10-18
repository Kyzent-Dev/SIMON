<?php

namespace App\Actions\MasterData;

use App\Models\Irban;
use Illuminate\Validation\Rule;

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
        $irban->fill([
            'nama' => $data['nama'], 'kode_nomor' => $data['kodeNomor'],
            'email_notifikasi' => $data['emailNotifikasi'] ?: null,
            // An empty list is stored as null.
            'email_tembusan' => $data['tembusan'] ? array_values($data['tembusan']) : null,
        ])->save();

        return $irban;
    }
}
