<?php

namespace App\Actions\MasterData;

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;

class ImportPegawai
{
    public function __construct(private SavePegawai $save) {}

    /** Upserts analyzed rows (status valid/warning) by NIP in one transaction; returns the count. */
    public function handle(array $rows): int
    {
        $rows = array_filter($rows, fn ($r) => $r['status'] !== 'error');

        return DB::transaction(function () use ($rows) {
            foreach ($rows as $r) {
                $this->save->handle(Pegawai::where('nip', $r['data']['nip'])->first(), $r['data']);
            }

            return count($rows);
        });
    }
}
