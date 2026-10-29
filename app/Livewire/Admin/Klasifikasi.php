<?php

namespace App\Livewire\Admin;

use App\Actions\MasterData\UpdateKlasifikasi;
use App\Models\Klasifikasi as Model;
use App\Services\ColorRules;
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

    /** Rekap Tahunan ribbon colour, 6 hex digits without '#'; empty = none. */
    public string $warna = '';

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
        $this->warna = (string) $k->warna;
        $this->open = true;
    }

    public function save(UpdateKlasifikasi $update): void
    {
        Gate::authorize('manage-master-data');
        $this->warna = ltrim(trim($this->warna), '#'); // a pasted "#BDD7EE" is accepted
        $data = $this->validate(UpdateKlasifikasi::rules(), ['warna.regex' => 'Warna harus 6 digit heksadesimal, mis. BDD7EE.'], ['syaratSelesai' => 'Syarat selesai', 'warna' => 'Warna']);
        $k = $update->handle(Model::findOrFail($this->editingId), $data);

        $this->open = false;
        $this->dispatch('toast', type: 'success', message: "Aturan {$k->nama} disimpan.");
    }

    /** Preview colours (`--type`/`--type-fg`) of a stored or typed hex, or null when it is not a 6-digit hex; the text colour is the one the workbook uses. */
    public static function pratinjau(?string $hex): ?array
    {
        $hex = ltrim(trim((string) $hex), '#');
        if (! preg_match('/^[0-9A-Fa-f]{6}$/', $hex)) {
            return null;
        }
        $warna = '#'.strtoupper($hex);

        return ['warna' => $warna, 'teks' => ColorRules::labelFor($warna) ?? ColorRules::DARK];
    }

    public function render()
    {
        Gate::authorize('manage-master-data');

        return view('livewire.admin.klasifikasi', [
            'rows' => Model::orderBy('id')->get(),
            'editing' => $this->editingId ? Model::find($this->editingId) : null,
            'pratinjau' => self::pratinjau($this->warna),
        ]);
    }
}
