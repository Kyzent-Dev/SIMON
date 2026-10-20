<?php

namespace App\Livewire\Notifikasi;

use App\Models\User;
use App\Support\NotifikasiPengguna;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/** Header bell (_shared.md §2): unread count, 8 newest in a dropdown, mark read. Own notifications only. */
class Lonceng extends Component
{
    public string $tab = 'semua';

    /** Set by the dropdown (Alpine): the 8-item list is only queried while it is open. */
    public bool $terbuka = false;

    /** Unread count at the previous render; an increase plays the ring and is announced. */
    #[Locked]
    public int $terakhir = 0;

    public function mount(): void
    {
        $this->terakhir = $this->pengguna()->unreadNotifications()->count();
    }

    public function updatedTab(): void
    {
        if (! in_array($this->tab, ['semua', 'belum'], true)) {
            $this->tab = 'semua';
        }
    }

    private function pengguna(): User
    {
        return auth()->user() ?? abort(403);
    }

    /** wire:poll target. While the dropdown is open the render is skipped so the list the user is reading does not change. */
    public function periksa(bool $terbuka = false): void
    {
        $this->pengguna();
        if ($terbuka) {
            $this->skipRender();
        }
    }

    public function buka(string $id): void
    {
        $url = NotifikasiPengguna::buka($this->pengguna(), $id);
        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    public function tandaiSemuaDibaca(): void
    {
        NotifikasiPengguna::tandaiSemua($this->pengguna());
        $this->dispatch('lonceng-diperbarui');
    }

    /** The list page marked something read; the render re-reads the count (and lowers the baseline, so no ring). */
    #[On('notifikasi-diperbarui')]
    public function segarkan(): void
    {
        $this->pengguna();
    }

    public function render()
    {
        $user = $this->pengguna();
        $belum = $user->unreadNotifications()->count();
        $baru = max(0, $belum - $this->terakhir);
        $this->terakhir = $belum;
        if ($baru) {
            $this->dispatch('lonceng-dering');
        }

        return view('livewire.notifikasi.lonceng', [
            'belum' => $belum,
            'baru' => $baru,
            'notifikasi' => $this->terbuka ? ($this->tab === 'belum' ? $user->unreadNotifications() : $user->notifications())->latest()->latest('id')->limit(8)->get() : collect(),
        ]);
    }
}
