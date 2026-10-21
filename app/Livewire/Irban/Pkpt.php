<?php

namespace App\Livewire\Irban;

use App\Actions\Pkpt\AjukanRevisiJadwal;
use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Enums\StatusRevisi;
use App\Models\Pkpt as PkptModel;
use App\Models\PkptItem;
use App\Models\User;
use App\Services\KalenderPkpt;
use App\Services\KesesuaianPkpt;
use App\Services\WilayahPengawasan;
use App\Support\DiinputOleh;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Irban "Jadwal PKPT": the own irban's items of a Disahkan PKPT + "Ajukan Revisi Jadwal" modal. Rules live in AjukanRevisiJadwal. */
#[Title('Jadwal PKPT')]
class Pkpt extends Component
{
    private const FIELDS = ['tanggal_mulai', 'tanggal_selesai', 'alasan', 'diinput_oleh'];

    #[Url]
    public string $tahun = '';

    /** kalender | tabel; anything else falls back to kalender. */
    #[Url]
    public string $tampilan = 'kalender';

    public bool $open = false;

    public ?int $itemId = null;

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public string $alasan = '';

    public ?int $diinput_oleh = null;

    /** Diinput oleh shows the combobox instead of the prefilled chip. */
    public bool $ganti = false;

    public function mount(): void
    {
        $this->irban();
        $this->tahun = $this->tahun !== '' ? $this->tahun : (string) now('Asia/Jakarta')->year;
    }

    public function buka(int $id, KesesuaianPkpt $kesesuaian): void
    {
        $user = $this->irban();
        $item = $this->item($id);
        if ($item->revisi()->where('status', StatusRevisi::Menunggu)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Item ini masih punya revisi yang menunggu persetujuan.');

            return;
        }
        [$mulai, $selesai] = $kesesuaian->rentang($item);
        $this->resetValidation();
        $this->itemId = $item->id;
        $this->tanggal_mulai = $mulai->toDateString();
        $this->tanggal_selesai = $selesai->toDateString();
        $this->alasan = '';
        $this->diinput_oleh = DiinputOleh::terakhir($user);
        $this->ganti = false;
        $this->open = true;
    }

    public function gantiPenginput(): void
    {
        $this->irban();
        $this->ganti = true;
    }

    public function kirim(AjukanRevisiJadwal $action): void
    {
        $user = $this->irban();
        $item = $this->item((int) $this->itemId);
        $this->resetValidation();
        try {
            $action->handle($user, $item, $this->only(self::FIELDS));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                if (in_array($key, self::FIELDS, true)) {
                    $this->addError($key, $messages[0]);
                } else {
                    $this->open = false;
                    $this->dispatch('toast', type: 'error', message: $messages[0]);
                }
            }

            return;
        }
        if ($this->diinput_oleh) {
            DiinputOleh::ingat($user, $this->diinput_oleh);
        }
        $this->open = false;
        // The trigger becomes a disabled button, so the view moves focus to the item's "menunggu" reason instead.
        $this->dispatch('revisi-terkirim', id: $item->id);
        $this->dispatch('toast', type: 'success', message: 'Revisi jadwal diajukan. Menunggu persetujuan pimpinan.');
    }

    /** Route middleware does not run on Livewire update requests, so every entry point checks the role. */
    private function irban(): User
    {
        $user = auth()->user();
        abort_unless($user?->role === Role::Irban, 403);

        return $user;
    }

    private function item(int $id): PkptItem
    {
        return PkptItem::where('irban_id', $this->irban()->irban_id)->findOrFail($id);
    }

    public function render(KesesuaianPkpt $kesesuaian, KalenderPkpt $kalender, WilayahPengawasan $wilayah)
    {
        $user = $this->irban();
        if (! in_array($this->tampilan, ['kalender', 'tabel'], true)) {
            $this->tampilan = 'kalender';
        }
        $pkpt = PkptModel::where('tahun', (int) $this->tahun)->first();
        $items = $pkpt?->status === StatusPkpt::Disahkan
            ? $pkpt->items()->where('irban_id', $user->irban_id)
                ->with(['jenisPenugasan', 'revisi' => fn ($q) => $q->with(['diputuskanOleh', 'diinputOleh', 'diajukanOleh'])->orderBy('created_at')->orderBy('id')])
                ->orderBy('tanggal_mulai')->orderBy('id')->get()
            : collect();
        $rentang = $items->mapWithKeys(fn (PkptItem $i) => [$i->id => $i->revisi->contains('status', StatusRevisi::Disetujui)
            ? $kesesuaian->rentang($i) : [$i->tanggal_mulai, $i->tanggal_selesai]]);
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
            'aktif' => $this->open && $this->itemId ? $items->firstWhere('id', $this->itemId) : null,
            'penginput' => $this->open ? DiinputOleh::pilihan($user) : collect(),
        ]);
    }
}
