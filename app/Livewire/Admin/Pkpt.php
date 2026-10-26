<?php

namespace App\Livewire\Admin;

use App\Actions\Pkpt\DeletePkpt;
use App\Actions\Pkpt\SavePkpt;
use App\Models\Pkpt as Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('PKPT')]
class Pkpt extends Component
{
    public bool $open = false;

    public ?int $editingId = null;

    public string $tahun = '';

    public string $nomor_sk = '';

    public string $tanggal_sk = '';

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
        $this->reset('editingId', 'nomor_sk', 'tanggal_sk');
        $this->tahun = (string) now()->year;
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $p = Model::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $p->id;
        $this->tahun = (string) $p->tahun;
        $this->nomor_sk = (string) $p->nomor_sk;
        $this->tanggal_sk = (string) $p->tanggal_sk?->toDateString();
        $this->open = true;
    }

    public function save(SavePkpt $action): void
    {
        Gate::authorize('manage-master-data');
        $this->resetValidation();
        try {
            $p = $action->handle($this->editingId ? Model::findOrFail($this->editingId) : null, [
                'tahun' => $this->tahun, 'nomor_sk' => $this->nomor_sk, 'tanggal_sk' => $this->tanggal_sk,
            ]);
        } catch (ValidationException $e) {
            // Field errors show under their input; anything else (e.g. `pkpt`: disahkan meanwhile) would be invisible in the form.
            foreach ($e->errors() as $key => $messages) {
                in_array($key, ['tahun', 'nomor_sk', 'tanggal_sk'], true)
                    ? $this->addError($key, $messages[0])
                    : $this->dispatch('toast', type: 'error', message: $messages[0]);
            }

            return;
        }
        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "PKPT {$p->tahun} disimpan.");
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-master-data');
        $this->deletingId = Model::findOrFail($id)->id;
        $this->deleteOpen = true;
    }

    public function delete(DeletePkpt $action): void
    {
        Gate::authorize('manage-master-data');
        $p = Model::findOrFail($this->deletingId);
        $this->deleteOpen = false;
        try {
            $action->handle($p);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first());

            return;
        }
        $this->dispatch('toast', type: 'success', message: "PKPT {$p->tahun} dihapus.");
    }

    public function render()
    {
        Gate::authorize('manage-master-data');

        return view('livewire.admin.pkpt', [
            'rows' => Model::withCount(['items' => fn ($q) => $q->aktif()])->orderByDesc('tahun')->get(),
            'deleting' => $this->deletingId ? Model::withCount('items')->find($this->deletingId) : null,
        ]);
    }
}
