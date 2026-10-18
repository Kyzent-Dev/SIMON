<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\SaveWilayahIrban;
use App\Enums\JenisObjek;
use App\Models\Irban;
use App\Models\IrbanWilayah;
use App\Models\Objek;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Wilayah Irban')]
class WilayahIrban extends Component
{
    #[Url]
    public int $tahun = 0;

    /** @var array<int, list<int>> irban id => selected objek ids (unsaved) */
    public array $selected = [];

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
        $this->updatedTahun();
    }

    public function updatedTahun(): void
    {
        if (! in_array($this->tahun, IrbanTahunan::years(), true)) {
            $this->tahun = IrbanTahunan::currentYear();
        }
        $this->resetErrorBag();
        $this->selected = [];
        foreach (IrbanWilayah::where('tahun', $this->tahun)->get() as $r) {
            $this->selected[$r->irban_id][] = $r->objek_id;
        }
    }

    public function save(int $irbanId, SaveWilayahIrban $action): void
    {
        Gate::authorize('manage-master-data');
        $this->resetErrorBag();
        $irban = Irban::findOrFail($irbanId);
        try {
            $action->handle($irban, $this->tahun, array_values($this->selected[$irbanId] ?? []));
        } catch (ValidationException $e) {
            $this->addError("selected.{$irbanId}", $e->errors()['objek'][0]);

            return;
        }
        $this->dispatch('toast', type: 'success', message: "Wilayah {$irban->nama} tahun {$this->tahun} disimpan.");
    }

    public function render()
    {
        $saved = IrbanWilayah::with('objek')->where('tahun', $this->tahun)->get();
        $owner = $saved->pluck('irban_id', 'objek_id');
        $irbans = Irban::orderBy('id')->get();
        $objek = Objek::whereIn('jenis', array_map(fn ($j) => $j->value, JenisObjek::wilayahTypes()))->orderBy('nama')->get();

        // Per irban: its own saved objek plus every unassigned objek stay selectable; objek saved to another irban are listed as disabled-by-label and filtered out.
        $options = [];
        $taken = [];
        foreach ($irbans as $irban) {
            $options[$irban->id] = $objek->filter(fn ($o) => ! isset($owner[$o->id]) || $owner[$o->id] === $irban->id)
                ->mapWithKeys(fn ($o) => [$o->id => $o->nama.' ('.$o->jenis->label().')'])->all();
            $taken[$irban->id] = $objek->filter(fn ($o) => isset($owner[$o->id]) && $owner[$o->id] !== $irban->id)
                ->map(fn ($o) => $o->nama.' ('.$irbans->firstWhere('id', $owner[$o->id])?->nama.')')->values()->all();
        }

        $summary = $irbans->map(function ($irban) use ($saved) {
            $mine = $saved->where('irban_id', $irban->id)->pluck('objek')->sortBy('nama');
            $names = fn (array $types) => $mine->filter(fn ($o) => in_array($o->jenis, $types, true))->pluck('nama')->all();

            return [
                'irban' => $irban->nama,
                'kecamatan' => $names([JenisObjek::Kecamatan]),
                'dinas' => $names([JenisObjek::DinasBadan]),
                'bumd' => $names([JenisObjek::Bumd, JenisObjek::Rsud]),
            ];
        });

        return view('livewire.admin.wilayah-irban', [
            'irbans' => $irbans,
            'years' => array_combine(IrbanTahunan::years(), IrbanTahunan::years()),
            'options' => $options,
            'taken' => $taken,
            'summary' => $summary,
        ]);
    }
}
