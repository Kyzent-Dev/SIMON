<?php

namespace App\Livewire\Notifikasi;

use App\Models\User;
use App\Support\NotifikasiPengguna;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Notification list page for every role (_shared.md §2): own notifications only, tabs, bulk + per-item mark read. */
#[Title('Notifikasi')]
#[Layout('layouts::app', ['breadcrumbs' => ['Dasbor' => null, 'Notifikasi' => null]])]
class Daftar extends Component
{
    use WithPagination;

    #[Url]
    public string $tab = 'semua';

    /** @var list<string> */
    public array $terpilih = [];

    /** Bound to x-ui.pagination's select, so not #[Locked]; tampered values are clamped. */
    public int $perPage = 20;

    public function mount(): void
    {
        $this->pengguna();
        $this->normalisasi();
    }

    public function updated(string $name): void
    {
        $this->pengguna();
        $this->normalisasi();
        if ($name !== 'terpilih') {
            $this->resetPage();
        }
    }

    /** Selection is "this page only": leaving the page drops it. */
    public function updatedPaginators(): void
    {
        $this->terpilih = [];
    }

    private function normalisasi(): void
    {
        if (! in_array($this->tab, ['semua', 'belum'], true)) {
            $this->tab = 'semua';
        }
        if (! in_array($this->perPage, [20, 50, 100], true)) {
            $this->perPage = 20;
        }
        $this->terpilih = array_values(array_filter($this->terpilih, 'is_string'));
    }

    private function pengguna(): User
    {
        return auth()->user() ?? abort(403);
    }

    public function tandaiDibaca(string $id): void
    {
        // The clicked button disappears; keep focus on the item's link (it stays on the Semua tab).
        $this->selesai(NotifikasiPengguna::tandaiDibaca($this->pengguna(), [$id]), 'notif-'.$id);
    }

    public function tandaiTerpilih(): void
    {
        $n = NotifikasiPengguna::tandaiDibaca($this->pengguna(), $this->terpilih);
        $this->terpilih = [];
        $this->selesai($n);
    }

    public function tandaiSemua(): void
    {
        $this->terpilih = [];
        $this->selesai(NotifikasiPengguna::tandaiSemua($this->pengguna()));
    }

    public function buka(string $id): void
    {
        $url = NotifikasiPengguna::buka($this->pengguna(), $id);
        if ($url) {
            $this->redirect($url, navigate: true);
        }
    }

    /** The bell marked something read; the render below re-reads the list and count. */
    #[On('lonceng-diperbarui')]
    public function segarkan(): void
    {
        $this->pengguna();
    }

    private function selesai(int $n, string $fokus = 'judul-notifikasi'): void
    {
        $this->dispatch('notifikasi-diperbarui');
        $this->dispatch('toast', type: $n ? 'success' : 'info', message: $n ? "{$n} notifikasi ditandai dibaca" : 'Tidak ada notifikasi yang perlu ditandai dibaca');
        // The clicked control disappears or gets disabled after any mark action, so focus always moves to a stable target;
        // the item link is gone from the "Belum dibaca" tab, so the h1 is used there.
        $this->dispatch('fokus-ke', id: $this->tab === 'belum' ? 'judul-notifikasi' : $fokus);
    }

    private function query()
    {
        $user = $this->pengguna();

        return ($this->tab === 'belum' ? $user->unreadNotifications() : $user->notifications())->latest()->latest('id');
    }

    public function render()
    {
        $query = $this->query();
        $terakhir = max(1, (int) ceil($query->count() / $this->perPage));
        if ($this->getPage() > $terakhir) {
            $this->setPage($terakhir);
        }

        return view('livewire.notifikasi.daftar', [
            'notifikasi' => $query->paginate($this->perPage),
            'belumDibaca' => $this->pengguna()->unreadNotifications()->count(),
        ]);
    }
}
