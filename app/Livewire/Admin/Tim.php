<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\SaveTim;
use App\Enums\PeranTim;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Tim as Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Tim')]
class Tim extends Component
{
    #[Url]
    public int $irbanId = 0;

    #[Url]
    public int $tahun = 0;

    public bool $open = false;

    public ?int $editingId = null;

    public string $nama = '';

    /** @var list<array{pegawai_id: int, peran_default: string}> display order = urutan */
    public array $members = [];

    public int|string|null $newPegawai = null;

    public string $newPeran = 'anggota';

    public bool $deleteOpen = false;

    public ?int $deletingId = null;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
        $this->updatedTahun();
        if (! Irban::whereKey($this->irbanId)->exists()) {
            $this->irbanId = (int) Irban::orderBy('id')->value('id');
        }
    }

    public function updatedTahun(): void
    {
        if (! in_array($this->tahun, IrbanTahunan::years(), true)) {
            $this->tahun = IrbanTahunan::currentYear();
        }
    }

    public function create(): void
    {
        Gate::authorize('manage-master-data');
        $this->resetValidation();
        $this->reset('editingId', 'members', 'newPegawai');
        $this->newPeran = 'anggota';
        $n = Model::where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun])->count() + 1;
        while (Model::where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun, 'nama' => "Tim {$n}"])->exists()) {
            $n++;
        }
        $this->nama = "Tim {$n}";
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $tim = Model::where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun])->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $tim->id;
        $this->nama = $tim->nama;
        $this->members = $tim->anggota->map(fn ($m) => ['pegawai_id' => $m->pegawai_id, 'peran_default' => $m->peran_default->value])->all();
        $this->reset('newPegawai');
        $this->newPeran = 'anggota';
        $this->open = true;
    }

    public function addMember(): void
    {
        Gate::authorize('manage-master-data');
        $id = (int) $this->newPegawai;
        $this->newPegawai = null;
        if ($id && ! in_array($id, array_column($this->members, 'pegawai_id'), true) && Pegawai::where('aktif', true)->whereKey($id)->exists()) {
            $this->members[] = ['pegawai_id' => $id, 'peran_default' => PeranTim::tryFrom($this->newPeran)?->value ?? 'anggota'];
        }
    }

    public function removeMember(int $index): void
    {
        Gate::authorize('manage-master-data');
        unset($this->members[$index]);
        $this->members = array_values($this->members);
    }

    public function moveUp(int $index): void
    {
        Gate::authorize('manage-master-data');
        $this->swap($index, $index - 1, 'naikkan', 0);
    }

    public function moveDown(int $index): void
    {
        Gate::authorize('manage-master-data');
        $this->swap($index, $index + 1, 'turunkan', count($this->members) - 1);
    }

    /** Swaps two rows, then asks the browser to put focus back on the moved row's button (the DOM move drops it); at the end of the list the opposite button takes it. */
    private function swap(int $a, int $b, string $button, int $edge): void
    {
        if (isset($this->members[$a], $this->members[$b])) {
            [$this->members[$a], $this->members[$b]] = [$this->members[$b], $this->members[$a]];
            $other = $button === 'naikkan' ? 'turunkan' : 'naikkan';
            $this->dispatch('tim-focus', id: ($b === $edge ? $other : $button).'-'.$this->members[$b]['pegawai_id']);
        }
    }

    public function save(SaveTim $action): void
    {
        Gate::authorize('manage-master-data');
        $this->resetErrorBag();
        try {
            $tim = $action->handle(
                Irban::findOrFail($this->irbanId),
                $this->tahun,
                $this->editingId ? Model::where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun])->findOrFail($this->editingId) : null,
                $this->nama,
                $this->members,
            );
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError($key, $message);
                }
            }

            return;
        }
        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "{$tim->nama} disimpan.");
    }

    public function confirmDelete(int $id): void
    {
        Gate::authorize('manage-master-data');
        $this->deletingId = Model::where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun])->findOrFail($id)->id;
        $this->deleteOpen = true;
    }

    public function delete(): void
    {
        Gate::authorize('manage-master-data');
        $tim = Model::where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun])->findOrFail($this->deletingId);
        $this->deleteOpen = false;
        $tim->delete();
        $this->dispatch('toast', type: 'success', message: "{$tim->nama} dihapus.");
    }

    public function render()
    {
        $teams = Model::with('anggota.pegawai')->where(['irban_id' => $this->irbanId, 'tahun' => $this->tahun])->orderBy('id')->get();
        // Active pegawai, plus members already on the form (so a deactivated saved member keeps its label).
        $options = Pegawai::where('aktif', true)->orWhereIn('id', array_column($this->members, 'pegawai_id'))->orderBy('nama_bergelar')->pluck('nama_bergelar', 'id')->all();
        $count = array_count_values(array_column($this->members, 'peran_default'));

        return view('livewire.admin.tim', [
            'irbans' => Irban::orderBy('id')->pluck('nama', 'id')->all(),
            'years' => array_combine(IrbanTahunan::years(), IrbanTahunan::years()),
            'teams' => $teams,
            'options' => $options,
            'perans' => PeranTim::options(),
            'supervisorCount' => $count['supervisor_dalnis'] ?? 0,
            'ketuaCount' => $count['ketua'] ?? 0,
            'deleting' => $this->deletingId ? Model::find($this->deletingId) : null,
        ]);
    }
}
