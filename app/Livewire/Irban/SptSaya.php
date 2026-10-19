<?php

namespace App\Livewire\Irban;

use App\Enums\Role;
use App\Enums\StatusSpt;
use App\Models\Spt;
use App\Models\User;
use App\Services\PenandaSpt;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** Irban "SPT Saya": the own irban's SPT with status + year filters (irban.md). Table ≥1024, cards below. */
#[Title('SPT Saya')]
class SptSaya extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $tahun = '';

    /** Bound to x-ui.pagination's per-page select, so it cannot be #[Locked]; tampered values are clamped instead. */
    public int $perPage = 25;

    public function mount(): void
    {
        $this->irban();
        $this->tahun = $this->tahun !== '' ? $this->tahun : (string) now('Asia/Jakarta')->year;
    }

    public function updated(): void
    {
        $this->irban();
        if (! in_array($this->perPage, [25, 50, 100], true)) {
            $this->perPage = 25;
        }
        $this->resetPage();
    }

    private function irban(): User
    {
        $user = auth()->user();
        abort_unless($user?->role === Role::Irban && $user->irban_id, 403);

        return $user;
    }

    public function render(PenandaSpt $penanda)
    {
        $user = $this->irban();
        $tahun = (int) $this->tahun;
        $status = StatusSpt::tryFrom($this->status);
        $spt = Spt::with(['jenisPenugasan', 'pkptItem'])->where('irban_id', $user->irban_id)
            // Year of the assignment; drafts without dates count in the year they were created.
            ->where(fn ($q) => $q->whereYear('tanggal_mulai', $tahun)->orWhere(fn ($q) => $q->whereNull('tanggal_mulai')->whereYear('created_at', $tahun)))
            ->when($status, fn ($q) => $q->berstatus($status))
            ->latest('updated_at')->latest('id')
            ->paginate($this->perPage);
        $tahunList = collect(range(now('Asia/Jakarta')->year + 1, 2024))->mapWithKeys(fn ($t) => [$t => $t])->all();

        return view('livewire.irban.spt-saya', [
            'spt' => $spt,
            // shortcut: PenandaSpt::untuk still runs its revisi/dokumen exists queries per row (bounded by the page size); batch them with the Fase 8 SQL flag filters.
            'penanda' => $spt->getCollection()->mapWithKeys(fn (Spt $s) => [$s->id => $penanda->untuk($s)])->all(),
            'statusOptions' => collect(StatusSpt::cases())
                ->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
            'tahunList' => $tahunList,
            'filtered' => $status !== null,
        ]);
    }
}
