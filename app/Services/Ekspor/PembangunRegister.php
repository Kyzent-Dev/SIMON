<?php

namespace App\Services\Ekspor;

use App\Exports\ArrayExport;
use App\Models\Spt;
use App\Models\User;
use App\Services\RegisterNomor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

class PembangunRegister implements Pembangun
{
    public function otorisasi(User $user): void
    {
        Gate::forUser($user)->authorize('kelolaNomor', Spt::class);
    }

    public function filter(User $user, array $filter): array
    {
        $tahun = (int) ($filter['tahun'] ?? 0);

        return [
            'tahun' => $tahun >= 2000 ? $tahun : CarbonImmutable::now('Asia/Jakarta')->year,
            'cari' => (string) ($filter['cari'] ?? ''), 'status' => (string) ($filter['status'] ?? ''),
            'irban' => (string) ($filter['irban'] ?? ''), 'bulan' => (string) ($filter['bulan'] ?? ''),
        ];
    }

    public function jumlah(User $user, array $filter): int
    {
        return app(RegisterNomor::class)->query($filter)->count();
    }

    public function namaFile(User $user, array $filter): string
    {
        return "register-nomor-spt-{$filter['tahun']}";
    }

    public function export(User $user, array $filter): ArrayExport
    {
        return new ArrayExport(RegisterNomor::KOLOM, app(RegisterNomor::class)->semua($filter), ['A']);
    }

    public function html(User $user, array $filter): string
    {
        return app(RegisterNomor::class)->html($filter);
    }

    public function halaman(): string
    {
        return route('bagian-umum.register', absolute: false);
    }
}
