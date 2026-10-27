<?php

namespace App\Livewire\Spt;

use App\Actions\Komentar\BalasKomentar;
use App\Actions\Komentar\TulisKomentar;
use App\Actions\Komentar\TutupPermintaan;
use App\Enums\Role;
use App\Enums\StatusPermintaan;
use App\Models\Spt;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Komentar section of the SPT detail page (_shared.md §7, Fase 7). Child of Detail; reloads only its own data.
 * Collects input and calls TulisKomentar / BalasKomentar / TutupPermintaan, which authorize and hold every rule
 * (the UI reads TutupPermintaan::bisa()). Focus moves through the app layout's window `fokus-ke` listener, not the host page's.
 */
class Komentar extends Component
{
    #[Locked]
    public int $sptId;

    /** Composer (pimpinan). */
    public string $isi = '';

    public bool $minta_penjelasan = false;

    /** Root id whose inline reply form is open (null = none). Not locked: the action re-checks it against this SPT. */
    public ?int $balasUntuk = null;

    public string $balasan = '';

    public ?int $diinput_oleh = null;

    public bool $ganti = false;

    public function mount(Spt $spt): void
    {
        Gate::authorize('view', $spt);
        $this->sptId = $spt->id;
    }

    public function bukaBalas(int $id): void
    {
        $spt = $this->spt();
        Gate::authorize('balasKomentar', $spt);
        if (! $spt->komentar()->whereNull('parent_id')->whereKey($id)->exists()) {
            $this->galatUmum('Komentar yang dibalas tidak ditemukan.');

            return;
        }
        $this->resetValidation();
        $this->reset('balasan', 'ganti');
        $this->diinput_oleh = DiinputOleh::terakhir(auth()->user());
        $this->balasUntuk = $id;
        $this->dispatch('fokus-ke', id: 'balasan');
    }

    public function batalBalas(): void
    {
        Gate::authorize('balasKomentar', $this->spt());
        $this->fokusBalas();
    }

    public function gantiPenginput(): void
    {
        Gate::authorize('balasKomentar', $this->spt());
        $this->ganti = true;
    }

    public function kirimKomentar(TulisKomentar $aksi): void
    {
        Gate::authorize('komentari', $this->spt());
        $baru = $this->jalankan(fn (Spt $spt) => $aksi->handle(auth()->user(), $spt, $this->only('isi', 'minta_penjelasan')), ['isi' => 'isi']);
        if ($baru) {
            $this->reset('isi', 'minta_penjelasan');
            $this->dispatch('toast', type: 'success', message: 'Komentar dikirim');
            $this->dispatch('fokus-ke', id: "komentar-{$baru->id}");
        }
    }

    public function kirimBalasan(BalasKomentar $aksi): void
    {
        Gate::authorize('balasKomentar', $this->spt());
        $data = ['parent_id' => $this->balasUntuk, 'isi' => $this->balasan, 'diinput_oleh' => $this->diinput_oleh];
        if ($this->jalankan(fn (Spt $spt) => $aksi->handle(auth()->user(), $spt, $data), ['isi' => 'balasan', 'diinput_oleh' => 'diinput_oleh'])) {
            $this->dispatch('toast', type: 'success', message: 'Balasan dikirim');
            $this->fokusBalas();
        }
    }

    public function tutup(TutupPermintaan $aksi, int $id): void
    {
        Gate::authorize('tutupPermintaan', $this->spt());
        if ($this->jalankan(fn (Spt $spt) => $aksi->handle(auth()->user(), $spt, ['komentar_id' => $id]), [])) {
            $this->dispatch('toast', type: 'success', message: 'Permintaan penjelasan ditutup');
            $this->dispatch('fokus-ke', id: "label-permintaan-{$id}"); // the button is gone, the label stays
        }
    }

    /** Runs an action; error keys in `$peta` (action key => form field) stay inline, any other key (`komentar`, `spt`) becomes a toast. */
    private function jalankan(callable $fn, array $peta): mixed
    {
        $spt = $this->spt();
        $this->resetValidation();
        try {
            return $fn($spt);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                isset($peta[$key]) ? $this->addError($peta[$key], $messages[0]) : $this->galatUmum($messages[0]);
            }

            return null;
        }
    }

    private function galatUmum(string $pesan): void
    {
        $this->dispatch('toast', type: 'error', message: $pesan);
        $this->dispatch('fokus-ke', id: 'judul-komentar');
    }

    /** Close the reply form and return focus to its thread's Balas button. */
    private function fokusBalas(): void
    {
        if ($this->balasUntuk) {
            $this->dispatch('fokus-ke', id: "balas-{$this->balasUntuk}");
        }
        $this->reset('balasUntuk', 'balasan', 'ganti');
        $this->resetValidation();
    }

    private function spt(): Spt
    {
        $spt = Spt::findOrFail($this->sptId);
        Gate::authorize('view', $spt);

        return $spt;
    }

    public function render(TutupPermintaan $tutup)
    {
        $spt = $this->spt();
        $user = auth()->user();
        $utas = $spt->komentar()->whereNull('parent_id')->oldest('id')
            ->with(['penulis', 'diinputOleh', 'ditutupOleh', 'balasan.penulis', 'balasan.diinputOleh'])->get();
        $bisaBalas = $user->can('balasKomentar', $spt);
        $bisaTutup = $user->can('tutupPermintaan', $spt);

        return view('livewire.spt.komentar', [
            'utas' => $utas,
            'jumlah' => $utas->count() + $utas->sum(fn ($r) => $r->balasan->count()),
            'blokirTutup' => $bisaTutup ? $utas->where('minta_penjelasan', true)->where('status_permintaan', StatusPermintaan::Terbuka)->mapWithKeys(fn ($r) => [$r->id => $tutup->bisa($r)]) : collect(),
            'bisaKomentari' => $user->can('komentari', $spt),
            'bisaBalas' => $bisaBalas,
            'bisaTutup' => $bisaTutup,
            'irban' => $user->role === Role::Irban,
            'penginput' => $bisaBalas && $this->balasUntuk && $user->role === Role::Irban ? DiinputOleh::pilihan($user) : collect(),
        ]);
    }
}
