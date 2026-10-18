<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\DeletePegawai;
use App\Actions\MasterData\SavePegawai;
use App\Models\Pegawai as Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Pegawai')]
class Pegawai extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'semua')]
    public string $filter = 'semua';

    #[Url(except: 'nama')]
    public string $sortBy = 'nama';

    #[Url(except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(except: 25)]
    public int $perPage = 25;

    public bool $open = false;

    public ?int $editingId = null;

    public string $nama = '';

    public string $nip = '';

    public string $pangkat = '';

    public string $golongan = '';

    public string $jabatan = '';

    public bool $aktif = true;

    public bool $deleteOpen = false;

    public ?int $deletingId = null;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
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

    public function sort(string $column): void
    {
        Gate::authorize('manage-master-data');
        if (! in_array($column, ['nama', 'nip'], true)) {
            return;
        }
        $this->sortDir = $this->sortBy === $column && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $column;
        $this->resetPage();
    }

    public function create(): void
    {
        Gate::authorize('manage-master-data');
        $this->resetValidation();
        $this->reset('editingId', 'nama', 'nip', 'pangkat', 'golongan', 'jabatan');
        $this->aktif = true;
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $p = Model::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $p->id;
        $this->nama = $p->nama_bergelar;
        $this->nip = $p->nip;
        $this->pangkat = (string) $p->pangkat;
        $this->golongan = (string) $p->golongan;
        $this->jabatan = (string) $p->jabatan;
        $this->aktif = $p->aktif;
        $this->open = true;
    }

    public function save(SavePegawai $action): void
    {
        Gate::authorize('manage-master-data');
        $data = $this->validate(SavePegawai::rules($this->editingId), SavePegawai::messages(), ['nama' => 'Nama bergelar']);
        $p = $action->handle($this->editingId ? Model::findOrFail($this->editingId) : null, $data);

        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "Pegawai {$p->nama_bergelar} disimpan.");
    }

    public function toggleAktif(int $id): void
    {
        Gate::authorize('manage-master-data');
        $p = Model::findOrFail($id);
        $p->update(['aktif' => ! $p->aktif]);
        $this->dispatch('toast', type: 'success', message: $p->aktif ? "{$p->nama_bergelar} diaktifkan." : "{$p->nama_bergelar} dinonaktifkan.");
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-master-data');
        $this->deletingId = Model::findOrFail($id)->id;
        $this->deleteOpen = true;
    }

    public function delete(DeletePegawai $action): void
    {
        Gate::authorize('manage-master-data');
        $p = Model::findOrFail($this->deletingId);
        $this->deleteOpen = false;
        try {
            $action->handle($p);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->errors()['delete'][0]);

            return;
        }
        $this->dispatch('toast', type: 'success', message: "Pegawai {$p->nama_bergelar} dihapus.");
    }

    public function render()
    {
        // Escape LIKE wildcards so "_" and "%" are searched literally.
        $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($this->search));
        $perPage = in_array($this->perPage, [25, 50, 100], true) ? $this->perPage : 25;
        $query = fn () => Model::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereRaw("nama_bergelar like ? escape '!'", ["%{$term}%"])->orWhereRaw("nip like ? escape '!'", ["%{$term}%"])))
            ->when($this->filter === 'aktif', fn ($q) => $q->where('aktif', true))
            ->when($this->filter === 'nonaktif', fn ($q) => $q->where('aktif', false))
            ->orderBy($this->sortBy === 'nip' ? 'nip' : 'nama_bergelar', $this->sortDir === 'desc' ? 'desc' : 'asc')
            ->paginate($perPage);

        $rows = $query();
        if ($rows->isEmpty() && $rows->currentPage() > 1) { // e.g. the last row of the last page was deleted
            $this->setPage($rows->lastPage());
            $rows = $query();
        }

        return view('livewire.admin.pegawai', [
            'rows' => $rows,
            'total' => Model::count(),
            'deleting' => $this->deletingId ? Model::find($this->deletingId) : null,
        ]);
    }
}
