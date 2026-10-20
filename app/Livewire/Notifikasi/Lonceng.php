<?php

namespace App\Livewire\Notifikasi;

use App\Models\User;
use App\Support\NotifikasiPengguna;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Header bell (_shared.md §2): unread count, 8 newest in a dropdown, mark read. Own notifications only. */
class Lonceng extends Component
{
    public string $tab = 'semua';

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

    public function tandaiDibaca(string $id): void
    {
        $url = NotifikasiPengguna::buka($this->pengguna(), $id);
        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    public function tandaiSemuaDibaca(): void
    {
        NotifikasiPengguna::tandaiSemua($this->pengguna());
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
            'notifikasi' => ($this->tab === 'belum' ? $user->unreadNotifications() : $user->notifications())->latest()->latest('id')->limit(8)->get(),
        ]);
    }
}
