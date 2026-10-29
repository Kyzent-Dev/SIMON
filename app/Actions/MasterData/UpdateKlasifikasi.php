<?php

namespace App\Actions\MasterData;

use App\Enums\SyaratSelesai;
use App\Models\Klasifikasi;
use Illuminate\Validation\Rule;

/** Admin edits a klasifikasi's rules only (PRD 9.4); kode and nama are fixed. */
class UpdateKlasifikasi
{
    public static function rules(): array
    {
        return [
            'wajibItemPkpt' => ['boolean'],
            'syaratSelesai' => ['required', Rule::enum(SyaratSelesai::class)],
            'tampilPublik' => ['boolean'],
            // Data colour of the Rekap Tahunan ribbon (spec 1.1.4 Task 4); empty clears it.
            'warna' => ['nullable', 'string', 'regex:/^[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function handle(Klasifikasi $klasifikasi, array $data): Klasifikasi
    {
        $klasifikasi->update([
            'wajib_item_pkpt' => $data['wajibItemPkpt'],
            'syarat_selesai' => $data['syaratSelesai'],
            'tampil_publik' => $data['tampilPublik'],
            // Callers that do not send the colour keep it.
            ...array_key_exists('warna', $data) ? ['warna' => filled($data['warna']) ? strtoupper($data['warna']) : null] : [],
        ]);

        return $klasifikasi;
    }
}
