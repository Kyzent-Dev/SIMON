<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\DeleteIrban;
use App\Actions\MasterData\SaveIrban;
use App\Models\Irban as Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Irban')]
class Irban extends Component
{
    public bool $open = false;

    public ?int $editingId = null;

    public string $nama = '';

    public string $kodeNomor = '';

    public string $emailNotifikasi = '';

    /** @var list<string> */
    public array $tembusan = [];

    public bool $deleteOpen = false;

    public ?int $deletingId = null;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
    }

    public function create(): void
    {
        Gate::authorize('manage-master-data');
        $this->resetValidation();
        $this->reset('editingId', 'nama', 'kodeNomor', 'emailNotifikasi', 'tembusan');
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $i = Model::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $i->id;
        $this->nama = $i->nama;
        $this->kodeNomor = $i->kode_nomor;
        $this->emailNotifikasi = (string) $i->email_notifikasi;
        $this->tembusan = $i->email_tembusan ?? [];
        $this->open = true;
    }

    public function addTembusan(): void
    {
        Gate::authorize('manage-master-data');
        $this->tembusan[] = '';
    }

    public function removeTembusan(int $index): void
    {
        Gate::authorize('manage-master-data');
        unset($this->tembusan[$index]);
        $this->tembusan = array_values($this->tembusan);
        $this->resetValidation('tembusan.*');
    }

    public function save(SaveIrban $action): void
    {
        Gate::authorize('manage-master-data');
        // Blank rows are dropped, not validated.
        $this->tembusan = array_values(array_filter(array_map('trim', $this->tembusan), fn ($e) => $e !== ''));
        $data = $this->validate(SaveIrban::rules($this->editingId), SaveIrban::messages(), ['kodeNomor' => 'Kode nomor', 'nama' => 'Nama']);
        $i = $action->handle($this->editingId ? Model::findOrFail($this->editingId) : null, $data);

        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "Irban {$i->nama} disimpan.");
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-master-data');
        $this->deletingId = Model::findOrFail($id)->id;
        $this->deleteOpen = true;
    }

    public function delete(DeleteIrban $action): void
    {
        Gate::authorize('manage-master-data');
        $i = Model::findOrFail($this->deletingId);
        $this->deleteOpen = false;
        try {
            $action->handle($i);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->errors()['delete'][0]);

            return;
        }
        $this->dispatch('toast', type: 'success', message: "Irban {$i->nama} dihapus.");
    }

    public function render()
    {
        return view('livewire.admin.irban', [
            'rows' => Model::orderBy('id')->get(),
            'deleting' => $this->deletingId ? Model::find($this->deletingId) : null,
        ]);
    }
}
