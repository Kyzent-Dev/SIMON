<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\LandingSlide;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dasbor Admin (admin.md "Dashboard"): health cards Pengguna aktif, Pegawai, PKPT {tahun}, Slide aktif + quick links from the sidebar definition.
 * Impor terakhir and Aktivitas terbaru come with Fase 10 (no placeholders). No motion.
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

        return view('livewire.admin.dasbor', [
            'tahun' => $tahun,
            // No `aktif` column on users: every account can log in, so all of them count.
            'pengguna' => User::count(),
            'pegawai' => Pegawai::where('aktif', true)->count(),
            'pkpt' => Pkpt::where('tahun', $tahun)->first(),
            'slideAktif' => LandingSlide::where('aktif', true)->count(),
            'slideTotal' => LandingSlide::count(),
            'links' => collect(Navigation::groups(Role::Admin))->pluck('items')->flatten(1)->reject(fn ($i) => $i['route'] === 'admin.dashboard')->values()->all(),
        ]);
    }
}
