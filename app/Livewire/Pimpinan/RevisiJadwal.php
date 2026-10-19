<?php

namespace App\Livewire\Pimpinan;

use App\Actions\Pkpt\PutuskanRevisiJadwal;
use App\Enums\Role;
use App\Enums\StatusRevisi;
use App\Models\Pkpt;
use App\Models\PkptItemRevisi;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Pimpinan "Revisi Jadwal": pending revision cards (Setujui/Tolak modals) + "Riwayat keputusan". Rules live in PutuskanRevisiJadwal. */
#[Title('Revisi Jadwal')]
class RevisiJadwal extends Component
{
    use WithPagination;

    /** Riwayat keputusan filter: the year of the item's PKPT. */
    #[Url]
    public string $tahun = '';

    #[Url(except: 25)]
    public int $perPage = 25;

    public ?int $revisiId = null;

    public bool $setujuiOpen = false;

    public bool $tolakOpen = false;

    public string $komentar = '';

    public function mount(): void
    {
        $this->pimpinan();
        $this->tahun = $this->tahun !== '' ? $this->tahun : (string) now('Asia/Jakarta')->year;
    }

    public function updatedTahun(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [25, 50, 100], true)) {
            $this->perPage = 25;
        }
        $this->resetPage();
    }

    public function setujui(int $id): void
    {
        if ($this->buka($id)) {
            $this->setujuiOpen = true;
        }
    }

    public function tolak(int $id): void
    {
        if ($this->buka($id)) {
            $this->tolakOpen = true;
        }
    }

    public function putuskan(bool $setujui, PutuskanRevisiJadwal $action): void
    {
        $user = $this->pimpinan();
        $revisi = PkptItemRevisi::findOrFail((int) $this->revisiId);
        $this->resetValidation();
        try {
            $action->handle($user, $revisi, $setujui, $this->komentar);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                if ($key === 'komentar') {
                    $this->addError('komentar', $messages[0]);
                } else {
                    $this->tutup();
                    $this->dispatch('toast', type: 'error', message: $messages[0]);
                }
            }

            return;
        }
        $this->tutup();
        // The card (and its trigger button) is gone, so the view moves focus to the list heading.
        $this->dispatch('revisi-diputuskan');
        $this->dispatch('toast', type: 'success', message: $setujui ? 'Revisi jadwal disetujui.' : 'Revisi jadwal ditolak.');
    }

    private function buka(int $id): bool
    {
        $this->pimpinan();
        $revisi = PkptItemRevisi::findOrFail($id);
        if ($revisi->status !== StatusRevisi::Menunggu) {
            $this->dispatch('toast', type: 'error', message: 'Revisi ini sudah diputuskan.');

            return false;
        }
        $this->resetValidation();
        $this->revisiId = $revisi->id;
        $this->komentar = '';

        return true;
    }

    private function tutup(): void
    {
        $this->setujuiOpen = $this->tolakOpen = false;
    }

    /** Route middleware does not run on Livewire update requests, so every entry point checks the role. */
    private function pimpinan(): User
    {
        $user = auth()->user();
        abort_unless($user?->role === Role::Pimpinan, 403);

        return $user;
    }

    public function render()
    {
        $this->pimpinan();
        $with = ['item.irban', 'item.jenisPenugasan', 'item.pkpt', 'diinputOleh', 'diajukanOleh'];
        $menunggu = PkptItemRevisi::with($with)->where('status', StatusRevisi::Menunggu)->orderBy('created_at')->orderBy('id')->get();
        $perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;
        $riwayat = PkptItemRevisi::with([...$with, 'diputuskanOleh'])
            ->whereIn('status', [StatusRevisi::Disetujui, StatusRevisi::Ditolak])
            ->whereHas('item.pkpt', fn ($q) => $q->where('tahun', (int) $this->tahun))
            ->orderByDesc('diputuskan_pada')->orderByDesc('id')
            ->paginate($perPage);
        $tahunList = Pkpt::pluck('tahun')->push(now('Asia/Jakarta')->year)
            ->unique()->sortDesc()->mapWithKeys(fn ($t) => [$t => $t])->all();

        return view('livewire.pimpinan.revisi-jadwal', [
            'menunggu' => $menunggu,
            'riwayat' => $riwayat,
            'tahunList' => $tahunList,
            'aktif' => $this->revisiId ? $menunggu->firstWhere('id', $this->revisiId) : null,
        ]);
    }
}
