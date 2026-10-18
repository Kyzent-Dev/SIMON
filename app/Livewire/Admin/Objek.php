<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\DeleteObjek;
use App\Actions\MasterData\SaveObjek;
use App\Enums\JenisObjek;
use App\Models\Objek as Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Objek Pemeriksaan')]
class Objek extends Component
{
    public const TABS = ['kecamatan' => 'Kecamatan', 'dinas_badan' => 'Dinas/Badan', 'bumd' => 'BUMD', 'rsud' => 'RSUD'];

    #[Url]
    public string $tab = 'kecamatan';

    #[Url]
    public string $search = '';

    public bool $open = false;

    public ?int $editingId = null;

    public string $nama = '';

    public string $jenis = 'kecamatan';

    public ?int $parentId = null;

    /** True when the drawer was opened from "Tambah anak": jenis is limited to the child types. */
    public bool $childOnly = false;

    public bool $deleteOpen = false;

    public ?int $deletingId = null;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
        $this->updatedTab();
    }

    public function updatedTab(): void
    {
        if (! isset(self::TABS[$this->tab])) {
            $this->tab = 'kecamatan';
        }
    }

    public function create(): void
    {
        Gate::authorize('manage-master-data');
        $this->resetValidation();
        $this->reset('editingId', 'nama', 'parentId', 'childOnly');
        $this->jenis = $this->tab;
        $this->open = true;
    }

    public function addChild(int $parentId): void
    {
        Gate::authorize('manage-master-data');
        $parent = Model::where('jenis', JenisObjek::Kecamatan->value)->findOrFail($parentId);
        $this->resetValidation();
        $this->reset('editingId', 'nama');
        $this->parentId = $parent->id;
        $this->childOnly = true;
        $this->jenis = JenisObjek::Desa->value;
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $o = Model::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $o->id;
        $this->nama = $o->nama;
        $this->jenis = $o->jenis->value;
        $this->parentId = $o->parent_id;
        $this->childOnly = false;
        $this->open = true;
    }

    public function save(SaveObjek $action): void
    {
        Gate::authorize('manage-master-data');
        $this->resetErrorBag();
        $type = JenisObjek::tryFrom($this->jenis);
        // The parent field is only shown for child types; drop a stale value for the others.
        $parent = $type?->needsParent() ? $this->parentId : null;
        try {
            $o = $action->handle($this->editingId ? Model::findOrFail($this->editingId) : null, $this->nama, $this->jenis, $parent);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        }
        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "{$o->nama} disimpan.");
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-master-data');
        $this->deletingId = Model::findOrFail($id)->id;
        $this->deleteOpen = true;
    }

    public function delete(DeleteObjek $action): void
    {
        Gate::authorize('manage-master-data');
        $o = Model::findOrFail($this->deletingId);
        $this->deleteOpen = false;
        try {
            $action->handle($o);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->errors()['delete'][0]);

            return;
        }
        $this->dispatch('toast', type: 'success', message: "{$o->nama} dihapus.");
    }

    public function render()
    {
        $term = trim($this->search);
        $like = fn ($q) => $q->where('nama', 'like', '%'.addcslashes($term, '%_\\').'%');

        if ($this->tab === 'kecamatan') {
            $rows = Model::where('jenis', 'kecamatan')
                ->when($term !== '', fn ($q) => $q->where(fn ($q) => $like($q)->orWhereHas('children', $like)))
                ->with('children')->orderBy('nama')->get();
            if ($term !== '') {
                // A matching kecamatan keeps all its children; otherwise only the matching children are shown.
                $rows->each(fn ($r) => mb_stripos($r->nama, $term) !== false
                    || $r->setRelation('children', $r->children->filter(fn ($c) => mb_stripos($c->nama, $term) !== false)->values()));
            }
        } else {
            $rows = Model::where('jenis', $this->tab)->when($term !== '', $like)->orderBy('nama')->get();
        }

        return view('livewire.admin.objek', [
            'tabs' => self::TABS,
            'rows' => $rows,
            'searching' => $term !== '',
            'jenisOptions' => JenisObjek::options($this->childOnly ? JenisObjek::childTypes() : null),
            'needsParent' => JenisObjek::tryFrom($this->jenis)?->needsParent() ?? false,
            'parentOptions' => Model::where('jenis', 'kecamatan')->orderBy('nama')->pluck('nama', 'id')->all(),
            'deleting' => $this->deletingId ? Model::find($this->deletingId) : null,
        ]);
    }
}
