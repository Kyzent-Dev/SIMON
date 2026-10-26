<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\SaveIrbanTahunan;
use App\Models\Irban;
use App\Models\IrbanTahunan as Row;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Komposisi Irban Tahunan')]
class IrbanTahunan extends Component
{
    #[Url]
    public int $tahun = 0;

    /** @var array<int, int|string|null> irban id => PPJ pegawai id */
    public array $ppj = [];

    /** @var array<int, list<int>> irban id => anggota pegawai ids */
    public array $anggota = [];

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
        $this->updatedTahun();
    }

    public static function currentYear(): int
    {
        return (int) now('Asia/Jakarta')->year;
    }

    /** Range current-2 to current+1. */
    public static function years(): array
    {
        $y = self::currentYear();

        return range($y - 2, $y + 1);
    }

    public function updatedTahun(): void
    {
        if (! in_array($this->tahun, self::years(), true)) {
            $this->tahun = self::currentYear();
        }
        $this->resetErrorBag();
        $this->ppj = $this->anggota = [];
        foreach (Row::with('anggota:id')->where('tahun', $this->tahun)->get() as $r) {
            $this->ppj[$r->irban_id] = $r->ppj_pegawai_id;
            $this->anggota[$r->irban_id] = $r->anggota->pluck('id')->all();
        }
    }

    public function save(int $irbanId, SaveIrbanTahunan $action): void
    {
        Gate::authorize('manage-master-data');
        $irban = Irban::findOrFail($irbanId);
        $this->resetErrorBag(["ppj.{$irbanId}", "anggota.{$irbanId}"]);
        try {
            $action->handle($irban, $this->tahun, ($this->ppj[$irbanId] ?? null) ?: null, $this->anggota[$irbanId] ?? []);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError("{$key}.{$irbanId}", $messages[0]);
            }

            return;
        }
        $this->dispatch('toast', type: 'success', message: "Komposisi {$irban->nama} tahun {$this->tahun} disimpan.");
    }

    public function render()
    {
        Gate::authorize('manage-master-data');
        $selected = collect($this->anggota)->flatten()->merge(array_filter($this->ppj))->all();
        // Active pegawai, plus any saved member deactivated later (so the chip keeps its label).
        $options = Pegawai::where('aktif', true)->orWhereIn('id', $selected)->orderBy('nama_bergelar')->pluck('nama_bergelar', 'id')->all();

        return view('livewire.admin.irban-tahunan', [
            'irbans' => Irban::orderBy('id')->get(),
            'options' => $options,
            'years' => array_combine(self::years(), self::years()),
        ]);
    }
}
