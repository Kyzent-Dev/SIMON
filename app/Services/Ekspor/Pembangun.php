<?php

namespace App\Services\Ekspor;

use App\Exports\ArrayExport;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/** One export jenis (Register, Monitoring, Rekap), shared by the immediate download and the queued job. Register it in Ekspor::PEMBANGUN. */
interface Pembangun
{
    /** @throws AuthorizationException */
    public function otorisasi(User $user): void;

    /** Validated and scoped to $user; the job calls it again with the stored filter. */
    public function filter(User $user, array $filter): array;

    public function jumlah(User $user, array $filter): int;

    /** Without extension. */
    public function namaFile(User $user, array $filter): string;

    public function export(User $user, array $filter): ArrayExport;

    public function html(User $user, array $filter): string;

    /** Relative URL of the page the export came from (failure notification). */
    public function halaman(): string;
}
