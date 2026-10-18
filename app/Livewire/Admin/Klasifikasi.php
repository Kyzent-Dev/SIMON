<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\UpdateKlasifikasi;
use App\Models\Klasifikasi as Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Klasifikasi & Aturan')]
class Klasifikasi extends Component
{
    public bool $open = false;

    public ?int $editingId = null;

    public bool $wajibItemPkpt = false;

    public string $syaratSelesai = '';

    public bool $tampilPublik = false;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $k = Model::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $k->id;
        $this->wajibItemPkpt = $k->wajib_item_pkpt;
        $this->syaratSelesai = $k->syarat_selesai->value;
        $this->tampilPublik = $k->tampil_publik;
        $this->open = true;
    }

    public function save(UpdateKlasifikasi $update): void
    {
        Gate::authorize('manage-master-data');
        $data = $this->validate(UpdateKlasifikasi::rules(), attributes: ['syaratSelesai' => 'Syarat selesai']);
        $k = $update->handle(Model::findOrFail($this->editingId), $data);

        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "Aturan {$k->nama} disimpan.");
    }

    public function render()
    {
        return view('livewire.admin.klasifikasi', [
            'rows' => Model::orderBy('id')->get(),
            'editing' => $this->editingId ? Model::find($this->editingId) : null,
        ]);
    }
}
