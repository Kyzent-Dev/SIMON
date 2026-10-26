<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\DeleteJenisPenugasan;
use App\Actions\MasterData\SaveJenisPenugasan;
use App\Models\JenisPenugasan as Model;
use App\Services\ColorRules;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Jenis Penugasan')]
class JenisPenugasan extends Component
{
    public bool $open = false;

    public ?int $editingId = null;

    public string $nama = '';

    /** '1'..'12' (curated swatch id) or 'custom'. */
    public string $swatch = '1';

    public string $custom = '';

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
        $this->reset('editingId', 'nama', 'custom');
        $this->swatch = '1';
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $j = Model::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $j->id;
        $this->nama = $j->nama;
        $match = collect(ColorRules::SWATCHES)->firstWhere('hex', $j->warna);
        $this->swatch = $match ? (string) $match['id'] : 'custom';
        $this->custom = $match ? '' : $j->warna;
        $this->open = true;
    }

    public function save(SaveJenisPenugasan $action): void
    {
        Gate::authorize('manage-master-data');
        $this->validate(SaveJenisPenugasan::rules($this->editingId), SaveJenisPenugasan::messages());
        try {
            $j = $action->handle($this->editingId ? Model::findOrFail($this->editingId) : null, $this->nama, $this->swatch, $this->custom);
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->errors());

            return;
        }
        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "Jenis penugasan {$j->nama} disimpan.");
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-master-data');
        $this->deletingId = Model::findOrFail($id)->id;
        $this->deleteOpen = true;
    }

    public function delete(DeleteJenisPenugasan $action): void
    {
        Gate::authorize('manage-master-data');
        $j = Model::findOrFail($this->deletingId);
        $this->deleteOpen = false;
        try {
            $action->handle($j);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->errors()['delete'][0]);

            return;
        }
        $this->dispatch('toast', type: 'success', message: "Jenis penugasan {$j->nama} dihapus.");
    }

    public function render()
    {
        Gate::authorize('manage-master-data');
        // Live preview and validation come from the same resolver the save uses.
        $customErrors = $this->swatch === 'custom' && trim($this->custom) !== '' ? ColorRules::validateCustom($this->custom) : [];
        $preview = null;
        try {
            [$warna, $teks] = SaveJenisPenugasan::resolveColor($this->swatch, $this->custom);
            $preview = ['warna' => $warna, 'teks' => $teks];
        } catch (ValidationException) {
        }

        return view('livewire.admin.jenis-penugasan', [
            'rows' => Model::orderBy('nama')->get(),
            'swatches' => ColorRules::SWATCHES,
            'customErrors' => $customErrors,
            'preview' => $preview,
            'reused' => $preview && Model::where('warna', $preview['warna'])->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))->exists(),
            'deleting' => $this->deletingId ? Model::find($this->deletingId) : null,
        ]);
    }
}
