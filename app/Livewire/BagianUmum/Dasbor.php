<?php

namespace App\Livewire\BagianUmum;

use App\Enums\KejadianSpt;
use App\Enums\StatusPengajuan;
use App\Models\Spt;
use App\Models\SptRiwayat;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Dasbor Bagian Umum (bagian-umum.md "Dashboard (minimal)"): two stat cards and the 5 oldest pending requests. No chart, no motion. */
#[Title('Dasbor Bagian Umum')]
class Dasbor extends Component
{
    public function mount(): void
    {
        Gate::authorize('kelolaNomor', Spt::class);
    }

    public function render()
    {
        Gate::authorize('kelolaNomor', Spt::class);
        $bulan = now('Asia/Jakarta');
        $menunggu = Spt::where('status_pengajuan', StatusPengajuan::MenungguNomor);

        return view('livewire.bagian-umum.dasbor', [
            'kartu' => [
                ['label' => 'Menunggu Keputusan', 'nilai' => (clone $menunggu)->count(), 'href' => route('bagian-umum.pengajuan')],
                // Issued + manually recorded numbers this Jakarta month (created_at is stored in Jakarta time).
                ['label' => 'Nomor Terbit Bulan Ini', 'href' => null, 'nilai' => SptRiwayat::whereIn('kejadian', [KejadianSpt::DITERIMA, KejadianSpt::DICATAT_MANUAL])
                    ->whereBetween('created_at', [$bulan->copy()->startOfMonth()->toDateTimeString(), $bulan->copy()->endOfMonth()->toDateTimeString()])->count()],
            ],
            'terlama' => $menunggu->with(['irban', 'jenisPenugasan'])->orderBy('diajukan_pada')->orderBy('id')->limit(5)->get(),
            'hariIni' => $bulan->copy()->startOfDay(),
        ]);
    }
}
