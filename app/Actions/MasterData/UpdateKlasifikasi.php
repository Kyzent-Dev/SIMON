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
        ];
    }

    public function handle(Klasifikasi $klasifikasi, array $data): Klasifikasi
    {
        $klasifikasi->update([
            'wajib_item_pkpt' => $data['wajibItemPkpt'],
            'syarat_selesai' => $data['syaratSelesai'],
            'tampil_publik' => $data['tampilPublik'],
        ]);

        return $klasifikasi;
    }
}
