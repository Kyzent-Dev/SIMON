<?php

namespace App\Livewire\Admin;

use App\Actions\Konten\AturAktifSlide;
use App\Actions\Konten\SimpanSlide;
use App\Actions\Konten\UrutkanSlide;
use App\Models\LandingSlide;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Slide Beranda (admin.md "Slide carousel manager"): card list ordered by urutan, drawer editor with a live hero preview. No animation beyond the drawer. */
#[Title('Slide Beranda')]
class Slide extends Component
{
    use WithFileUploads;

    public bool $open = false;

    #[Locked]
    public ?int $editingId = null;

    public string $judul = '';

    public string $kutipan = '';

    public string $pemilik_nama = '';

    public string $pemilik_jabatan = '';

    public string $alt = '';

    public bool $dekoratif = false;

    public bool $aktif = true;

    public ?TemporaryUploadedFile $gambar = null;

    public function mount(): void
    {
        Gate::authorize('manage-master-data');
    }

    public function create(): void
    {
        Gate::authorize('manage-master-data');
        $this->resetValidation();
        $this->reset('editingId', 'judul', 'kutipan', 'pemilik_nama', 'pemilik_jabatan', 'alt', 'dekoratif', 'aktif', 'gambar');
        $this->open = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-master-data');
        $s = LandingSlide::findOrFail($id);
        $this->resetValidation();
        $this->reset('gambar');
        $this->editingId = $s->id;
        $this->judul = $s->judul;
        $this->kutipan = (string) $s->kutipan;
        $this->pemilik_nama = (string) $s->pemilik_nama;
        $this->pemilik_jabatan = (string) $s->pemilik_jabatan;
        $this->alt = (string) $s->alt;
        $this->dekoratif = $s->dekoratif;
        $this->aktif = $s->aktif;
        $this->open = true;
    }

    /** Check the image as soon as it is uploaded, so a wrong file is reported before Simpan and never previewed. */
    public function updatedGambar(): void
    {
        Gate::authorize('manage-master-data');
        $this->validateOnly('gambar', SimpanSlide::rules($this->editingId === null), SimpanSlide::messages());
    }

    public function save(SimpanSlide $action): void
    {
        Gate::authorize('manage-master-data');
        $data = $this->validate(SimpanSlide::rules($this->editingId === null), SimpanSlide::messages());
        unset($data['gambar']);
        $action->handle($this->editingId ? LandingSlide::findOrFail($this->editingId) : null, $data, $this->gambar);
        $this->reset('gambar');
        $this->open = false;
        $this->dispatch('toast', type: 'success', message: 'Slide disimpan.');
    }

    public function naikkan(int $id, UrutkanSlide $action): void
    {
        $this->geser($id, -1, $action);
    }

    public function turunkan(int $id, UrutkanSlide $action): void
    {
        $this->geser($id, 1, $action);
    }

    /** Drag reorder (wire:sort): move slide $id to 0-based $posisi. */
    public function pindahkan(int $id, int $posisi, UrutkanSlide $action): void
    {
        Gate::authorize('manage-master-data');
        $ids = $this->urutanIds();
        $dari = array_search($id, $ids, true);
        abort_if($dari === false, 404);
        array_splice($ids, $dari, 1);
        array_splice($ids, max(0, min($posisi, count($ids))), 0, [$id]);
        $this->simpanUrutan($ids, $action);
    }

    public function aturAktif(int $id, bool $aktif, AturAktifSlide $action): void
    {
        Gate::authorize('manage-master-data');
        $action->handle(LandingSlide::findOrFail($id), $aktif);
        $this->dispatch('toast', type: 'success', message: $aktif ? 'Slide diaktifkan.' : 'Slide dinonaktifkan.');
    }

    private function geser(int $id, int $arah, UrutkanSlide $action): void
    {
        Gate::authorize('manage-master-data');
        $ids = $this->urutanIds();
        $i = array_search($id, $ids, true);
        abort_if($i === false, 404);
        $j = $i + $arah;
        if (! isset($ids[$j])) {
            return; // already at the end; the button is disabled
        }
        [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
        $this->simpanUrutan($ids, $action);
    }

    private function simpanUrutan(array $ids, UrutkanSlide $action): void
    {
        try {
            $action->handle($ids);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: $e->validator->errors()->first());

            return;
        }
        $this->dispatch('toast', type: 'success', message: 'Urutan slide disimpan.');
    }

    private function urutanIds(): array
    {
        return LandingSlide::orderBy('urutan')->orderBy('id')->pluck('id')->all();
    }

    public function render()
    {
        Gate::authorize('manage-master-data');
        $slides = LandingSlide::orderBy('urutan')->orderBy('id')->get();
        $editing = $this->editingId ? $slides->firstWhere('id', $this->editingId) : null;
        // Preview image: the new upload when it is a valid image, else the stored 960 variant.
        $pratinjau = null;
        if ($this->gambar && ! $this->getErrorBag()->has('gambar') && in_array(strtolower($this->gambar->getClientOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)) {
            try {
                $pratinjau = $this->gambar->temporaryUrl();
            } catch (\Throwable) {
            }
        }
        $pratinjau ??= $editing?->gambarUrl(960);

        return view('livewire.admin.slide', ['slides' => $slides, 'editing' => $editing, 'pratinjau' => $pratinjau]);
    }
}
