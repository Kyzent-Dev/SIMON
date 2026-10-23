<?php

namespace App\Livewire\Irban;

use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Models\Pkpt as PkptModel;
use App\Models\PkptItem;
use App\Models\User;
use App\Services\KalenderPkpt;
use App\Services\KesesuaianPkpt;
use App\Services\WilayahPengawasan;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Irban "Jadwal PKPT": the own irban's active items of a Disahkan PKPT (calendar or table). Read-only. */
#[Title('Jadwal PKPT')]
class Pkpt extends Component
{
    #[Url]
    public string $tahun = '';

    /** kalender | tabel; anything else falls back to kalender. */
    #[Url]
    public string $tampilan = 'kalender';

    public function mount(): void
    {
        $this->irban();
        $this->tahun = $this->tahun !== '' ? $this->tahun : (string) now('Asia/Jakarta')->year;
    }

    /** Route middleware does not run on Livewire update requests, so every entry point checks the role. */
    private function irban(): User
    {
        $user = auth()->user();
        abort_unless($user?->role === Role::Irban, 403);

        return $user;
    }

    public function render(KesesuaianPkpt $kesesuaian, KalenderPkpt $kalender, WilayahPengawasan $wilayah)
    {
        $user = $this->irban();
        if (! in_array($this->tampilan, ['kalender', 'tabel'], true)) {
            $this->tampilan = 'kalender';
        }
        $pkpt = PkptModel::where('tahun', (int) $this->tahun)->first();
        $items = $pkpt?->status === StatusPkpt::Disahkan
            ? $pkpt->items()->aktif()->where('irban_id', $user->irban_id)->with('jenisPenugasan')
                ->orderBy('tanggal_mulai')->orderBy('id')->get()
            : collect();
        $rentang = $items->mapWithKeys(fn (PkptItem $i) => [$i->id => $kesesuaian->rentang($i)]);
        $tahunList = PkptModel::pluck('tahun')->push(now('Asia/Jakarta')->year)
            ->unique()->sortDesc()->mapWithKeys(fn ($t) => [$t => $t])->all();

        $pakaiKalender = $this->tampilan === 'kalender' && $items->isNotEmpty();

        return view('livewire.irban.pkpt', [
            'kalender' => $pakaiKalender ? $kalender->untuk((int) $this->tahun, $user->irban_id) : [],
            'wilayah' => $pakaiKalender ? $wilayah->untuk((int) $this->tahun, $user->irban_id)[0] ?? null : null,
            'pkpt' => $pkpt,
            'items' => $items,
            'rentang' => $rentang,
            'tahunList' => $tahunList,
            'irbanNama' => $user->irban?->nama ?? 'irban ini',
        ]);
    }
}
