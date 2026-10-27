<?php

namespace App\Livewire\Pimpinan;

use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use App\Enums\StatusPerubahan;
use App\Livewire\Concerns\MenampilkanOperasi;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\PkptPerubahan;
use App\Services\PerubahanPkpt as AturanPerubahan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Pimpinan page of one Perubahan PKPT (Fase 9.2 Task 8): operasi before → after, impact (PerubahanPkpt::dampak), warnings (cek) and
 * Setujui / Tolak while `diajukan`. The package is re-read and re-authorized (`putuskan`) on every request. A failed re-validation keeps the
 * package `diajukan` and shows its reasons in an alert that takes focus; a stale decision closes the modals as a toast.
 */
class PerubahanPkptDetail extends Component
{
    use MenampilkanOperasi;

    #[Locked]
    public int $perubahanId;

    public bool $setujuiOpen = false;

    public bool $tolakOpen = false;

    public string $komentar = '';

    /** Reasons of a failed approval (re-validation); shown in an alert until the next attempt. @var list<string> */
    public array $alasanGagal = [];

    public function mount(PkptPerubahan $perubahan): void
    {
        $this->perubahanId = $perubahan->id;
        $this->paket();
    }

    public function setujui(PutuskanPerubahan $putuskan): void
    {
        $this->putuskan($putuskan, true, null);
    }

    public function tolak(PutuskanPerubahan $putuskan): void
    {
        $this->putuskan($putuskan, false, $this->komentar);
    }

    private function putuskan(PutuskanPerubahan $putuskan, bool $setujui, ?string $komentar): void
    {
        $p = $this->paket();
        $this->resetValidation();
        $this->alasanGagal = [];
        try {
            $putuskan->handle(auth()->user(), $p, $setujui, $komentar);
        } catch (ValidationException $e) {
            $galat = $e->errors();
            if (isset($galat['komentar'])) {
                $this->addError('komentar', $galat['komentar'][0]);
                $this->dispatch('fokus-ke', id: 'komentar'); // the Tolak modal's textarea

                return;
            }
            $this->reset('setujuiOpen', 'tolakOpen');
            $pesan = $galat['perubahan'] ?? [];
            if ($pesan === [PutuskanPerubahan::SUDAH_DIPUTUSKAN]) {
                $this->dispatch('toast', type: 'error', message: $pesan[0]);
                $this->dispatch('fokus-ke', id: 'judul-perubahan');
            } else {
                $this->alasanGagal = array_values($pesan);
                $this->dispatch('fokus-ke', id: 'alert-gagal');
            }

            return;
        }
        $this->reset('setujuiOpen', 'tolakOpen', 'komentar');
        $this->dispatch('toast', type: 'success', message: $setujui ? 'Perubahan PKPT disetujui.' : 'Perubahan PKPT ditolak.');
        $this->dispatch('fokus-ke', id: 'judul-perubahan');
    }

    private function paket(): PkptPerubahan
    {
        $p = PkptPerubahan::findOrFail($this->perubahanId);
        Gate::authorize('putuskan', $p);

        return $p;
    }

    public function render(AturanPerubahan $aturan)
    {
        $p = $this->paket();
        $p->load(['pkpt:id,tahun', 'pembuat:id,name', 'pemutus:id,name', 'operasi.item']);
        $irban = Irban::orderBy('id')->pluck('nama', 'id')->all();
        $jenis = JenisPenugasan::orderBy('nama')->get(['id', 'nama', 'warna'])->keyBy('id');
        $diajukan = $p->status === StatusPerubahan::Diajukan;
        $judul = 'Perubahan PKPT '.$p->pkpt->tahun.($p->nomor_urut ? " · Perubahan ke-{$p->nomor_urut}" : '');

        return view('livewire.pimpinan.perubahan-pkpt-detail', [
            'p' => $p,
            'judul' => $judul,
            'diajukan' => $diajukan,
            'operasi' => $p->operasi->map(fn ($op) => $this->tampilOperasi($op, $irban, $jenis)),
            'dampak' => $aturan->dampak($p),
            'cek' => $diajukan ? $aturan->cek($p) : [],
        ])->title($judul);
    }
}
