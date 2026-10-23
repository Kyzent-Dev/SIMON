<?php

namespace App\Livewire\Pimpinan;

use App\Enums\Role;
use App\Enums\StatusPerubahan;
use App\Models\PkptPerubahan;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pimpinan list of Perubahan PKPT packages (Fase 9.2 Task 8; pimpinan.md Approval list): "Menunggu persetujuan" (diajukan, oldest first)
 * and a paginated "Riwayat" (disetujui/ditolak). Read-only; the decision happens on the detail page. Role is re-checked on every request
 * because the Livewire update endpoint skips the route middleware.
 */
#[Title('Perubahan PKPT')]
class PerubahanPkpt extends Component
{
    use WithPagination;

    public int $perPage = 20;

    public function mount(): void
    {
        $this->izin();
    }

    public function render()
    {
        $this->izin();
        $dengan = fn ($q) => $q->with(['pkpt:id,tahun', 'pembuat:id,name'])->withCount('operasi');

        return view('livewire.pimpinan.perubahan-pkpt', [
            'menunggu' => $dengan(PkptPerubahan::query())->where('status', StatusPerubahan::Diajukan)->orderBy('diajukan_pada')->orderBy('id')->get(),
            'riwayat' => $dengan(PkptPerubahan::query())->whereIn('status', [StatusPerubahan::Disetujui, StatusPerubahan::Ditolak])
                ->orderByDesc('diputuskan_pada')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }

    private function izin(): void
    {
        abort_unless(auth()->user()?->role === Role::Pimpinan, 403);
    }
}
