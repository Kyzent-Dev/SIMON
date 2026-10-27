<?php

namespace App\Actions\MasterData;

use App\Models\Aktivitas;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ImportPegawai
{
    public function __construct(private SavePegawai $save) {}

    /** Upserts analyzed rows (status valid/warning) by NIP in one transaction; returns the count. */
    public function handle(User $admin, array $rows, string $berkas = ''): int
    {
        $rows = array_filter($rows, fn ($r) => $r['status'] !== 'error');

        try {
            $n = DB::transaction(function () use ($rows) {
                foreach ($rows as $r) {
                    $this->save->handle(Pegawai::where('nip', $r['data']['nip'])->first(), $r['data']);
                }

                return count($rows);
            });
        } catch (ValidationException) {
            // A NIP was inserted by someone else after the preview; the transaction rolled everything back.
            throw ValidationException::withMessages(['file' => 'Impor dibatalkan: ada NIP yang baru saja didaftarkan pengguna lain. Muat ulang pratinjau dan coba lagi.']);
        }
        if ($n > 0) {
            Aktivitas::catatImpor($admin, 'pegawai', $berkas, $n);
        }

        return $n;
    }
}
