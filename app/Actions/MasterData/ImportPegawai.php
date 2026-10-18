<?php

namespace App\Actions\MasterData;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportPegawai
{
    public function __construct(private SavePegawai $save) {}

    /** Upserts analyzed rows (status valid/warning) by NIP in one transaction; returns the count. */
    public function handle(array $rows): int
    {
        $rows = array_filter($rows, fn ($r) => $r['status'] !== 'error');

        try {
            return DB::transaction(function () use ($rows) {
                foreach ($rows as $r) {
                    $this->save->handle(Pegawai::where('nip', $r['data']['nip'])->first(), $r['data']);
                }

                return count($rows);
            });
        } catch (ValidationException) {
            // A NIP was inserted by someone else after the preview; the transaction rolled everything back.
            throw ValidationException::withMessages(['file' => 'Impor dibatalkan: ada NIP yang baru saja didaftarkan pengguna lain. Muat ulang pratinjau dan coba lagi.']);
        }
    }
}
