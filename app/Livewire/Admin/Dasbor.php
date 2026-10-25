<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Enums\StatusPerubahan;
use App\Models\Aktivitas;
use App\Models\LandingSlide;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\PkptPerubahan;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dasbor Admin (admin.md "Dashboard"): health cards Pengguna aktif, Pegawai, PKPT {tahun}, Slide aktif + quick links from the sidebar definition.
 * Aktivitas terbaru lists the 10 newest log rows (Fase 10; Impor terakhir comes with the import screen). Fase 9.2: "Perubahan menunggu persetujuan" (link to the PKPT
 * of the oldest submitted paket) and "SK belum dilengkapi" (same set as the daily SK reminder in NotifikasiHarian). No motion.
 */
#[Title('Dasbor Admin')]
class Dasbor extends Component
{
    public function mount(): void
    {
        Gate::authorize('manage-master-data');
    }

    public function render()
    {
        Gate::authorize('manage-master-data');
        $tahun = (int) now('Asia/Jakarta')->year;
        $menunggu = PkptPerubahan::where('status', StatusPerubahan::Diajukan);

        return view('livewire.admin.dasbor', [
            'tahun' => $tahun,
            // No `aktif` column on users: every account can log in, so all of them count.
            'pengguna' => User::count(),
            'pegawai' => Pegawai::where('aktif', true)->count(),
            'pkpt' => Pkpt::where('tahun', $tahun)->first(),
            'slideAktif' => LandingSlide::where('aktif', true)->count(),
            'slideTotal' => LandingSlide::count(),
            'menunggu' => (clone $menunggu)->count(),
            'menungguTertua' => (clone $menunggu)->orderBy('diajukan_pada')->orderBy('id')->value('pkpt_id'),
            'skBelum' => PkptPerubahan::query()->perluSk()->whereNull('pengingat_sk_dimatikan_pada')->count(),
            'aktivitas' => Aktivitas::with('causer')->latest('id')->limit(10)->get(),
            'links' => collect(Navigation::groups(Role::Admin))->pluck('items')->flatten(1)->reject(fn ($i) => $i['route'] === 'admin.dashboard')->values()->all(),
        ]);
    }
}
