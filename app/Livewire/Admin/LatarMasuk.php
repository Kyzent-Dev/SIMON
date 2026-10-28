<?php

namespace App\Livewire\Admin;

use App\Actions\Konten\AturLatarMasuk;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Latar Halaman Masuk (admin.md): one optional photo behind the login page's brand panel, with a live preview of that panel. */
#[Title('Latar Halaman Masuk')]
class LatarMasuk extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $foto_latar = null;

    public bool $hapusOpen = false;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
    }

    /** Check the file as soon as it is chosen, so a wrong file is reported before Simpan and never previewed. */
    public function updatedFotoLatar(): void
    {
        Gate::authorize('manage-master-data');
        $this->validateOnly('foto_latar', AturLatarMasuk::rules(), AturLatarMasuk::messages());
    }

    public function save(AturLatarMasuk $aksi): void
    {
        Gate::authorize('manage-master-data');
        $this->validate(AturLatarMasuk::rules(), AturLatarMasuk::messages());
        $aksi->ganti($this->foto_latar);
        $this->reset('foto_latar');
        $this->dispatch('toast', type: 'success', message: 'Foto latar disimpan.');
    }

    public function batal(): void
    {
        Gate::authorize('manage-master-data');
        $this->reset('foto_latar');
        $this->resetValidation();
    }

    public function confirmHapus(): void
    {
        Gate::authorize('manage-master-data');
        $this->hapusOpen = true;
    }

    public function hapus(AturLatarMasuk $aksi): void
    {
        Gate::authorize('manage-master-data');
        $this->hapusOpen = false;
        $aksi->hapus();
        $this->dispatch('toast', type: 'success', message: 'Foto latar dihapus. Halaman Masuk memakai latar bawaan.');
    }

    public function render(Settings $settings)
    {
        Gate::authorize('manage-master-data');
        $base = (string) $settings->get(AturLatarMasuk::KUNCI);
        $pratinjau = null;
        if ($this->foto_latar && ! $this->getErrorBag()->has('foto_latar')) {
            try {
                $pratinjau = $this->foto_latar->temporaryUrl();
            } catch (\Throwable) {
            }
        }

        return view('livewire.admin.latar-masuk', ['base' => $base, 'pratinjau' => $pratinjau]);
    }
}
