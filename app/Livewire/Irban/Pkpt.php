<?php

namespace App\Livewire\Irban;

use App\Actions\Pkpt\Usulan\AjukanUsulan;
use App\Enums\AksiUsulan;
use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Models\JenisPenugasan;
use App\Models\Pkpt as PkptModel;
use App\Models\PkptItem;
use App\Models\PkptUsulan;
use App\Models\User;
use App\Services\KalenderPkpt;
use App\Services\KesesuaianPkpt;
use App\Services\PerubahanPkpt;
use App\Services\WilayahPengawasan;
use App\Support\DiinputOleh;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Irban "Jadwal PKPT": the own irban's active items of a Disahkan PKPT (calendar or table), riwayat per item, the "Usulkan Perubahan" /
 * "Usulkan item baru" modal and the "Usulan saya" list. Rules live in AjukanUsulan; every lookup is scoped to the user's irban.
 */
#[Title('Jadwal PKPT')]
class Pkpt extends Component
{
    private const FIELDS = ['aksi_usulan', 'tanggal_mulai', 'tanggal_selesai', 'jenis_penugasan_id', 'alasan', 'diinput_oleh'];

    private const TAMPILAN = ['kalender', 'tabel', 'usulan'];

    #[Url]
    public string $tahun = '';

    /** kalender | tabel | usulan; anything else falls back to kalender. */
    #[Url]
    public string $tampilan = 'kalender';

    public bool $usulanOpen = false;

    /** Item of the open modal; null = "Usulkan item baru". */
    #[Locked]
    public ?int $usulanItemId = null;

    public string $aksi_usulan = '';

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public string $jenis_penugasan_id = '';

    public string $alasan = '';

    public ?int $diinput_oleh = null;

    /** Diinput oleh shows the combobox instead of the prefilled chip. */
    public bool $ganti = false;

    public function mount(): void
    {
        $this->irban();
        $this->tahun = $this->tahun !== '' ? $this->tahun : (string) now('Asia/Jakarta')->year;
    }

    /** Opens the modal for an own active item (404 otherwise) or, with null, for a new item of the year's Disahkan PKPT. */
    public function bukaUsulan(?int $id = null): void
    {
        $user = $this->irban();
        $this->resetValidation();
        $this->reset('tanggal_mulai', 'tanggal_selesai', 'jenis_penugasan_id', 'alasan');
        if ($id !== null) {
            if (! $item = $this->item($id)) {
                return;
            }
            $this->usulanItemId = $item->id;
            $this->aksi_usulan = AksiUsulan::Geser->value;
            $this->tanggal_mulai = $item->tanggal_mulai->toDateString();
            $this->tanggal_selesai = $item->tanggal_selesai->toDateString();
        } else {
            if (! $this->pkptDisahkan()) {
                $this->dispatch('toast', type: 'error', message: 'Usulan perubahan hanya untuk PKPT yang sudah disahkan.');

                return;
            }
            $this->usulanItemId = null;
            $this->aksi_usulan = AksiUsulan::Tambah->value;
        }
        $this->diinput_oleh = DiinputOleh::terakhir($user);
        $this->ganti = false;
        $this->usulanOpen = true;
    }

    public function gantiPenginput(): void
    {
        $this->irban();
        $this->ganti = true;
    }

    public function kirimUsulan(AjukanUsulan $action): void
    {
        $user = $this->irban();
        $item = $this->usulanItemId ? $this->item($this->usulanItemId) : null;
        if ($this->usulanItemId && ! $item) {
            return;
        }
        $pkpt = $item?->pkpt ?? $this->pkptDisahkan() ?? PkptModel::where('tahun', (int) $this->tahun)->firstOrFail();
        // The radio offers "Item baru" only without an item and the other kinds only with one.
        $aksi = $item ? ($this->aksi_usulan === AksiUsulan::Tambah->value ? '' : $this->aksi_usulan) : AksiUsulan::Tambah->value;
        $this->resetValidation();
        try {
            $action->handle($user, $pkpt, $item, ['aksi_usulan' => $aksi] + $this->only(array_slice(self::FIELDS, 1)));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                if (in_array($key, self::FIELDS, true)) {
                    $this->addError($key, $messages[0]);
                } else {
                    $this->usulanOpen = false;
                    $this->dispatch('toast', type: 'error', message: $messages[0]);
                    $this->dispatch('usulan-fokus', id: null);
                }
            }

            return;
        }
        $this->usulanOpen = false;
        $this->dispatch('usulan-fokus', id: $item?->id ?? 'baru');
        $this->dispatch('toast', type: 'success', message: 'Usulan perubahan terkirim ke Admin.');
    }

    /** Route middleware does not run on Livewire update requests, so every entry point checks the role. */
    private function irban(): User
    {
        $user = auth()->user();
        abort_unless($user?->role === Role::Irban, 403);

        return $user;
    }

    /** An active item of this irban in a Disahkan PKPT; null after a stale-tab toast (item removed or moved since); a foreign id is a 404. */
    private function item(int $id): ?PkptItem
    {
        try {
            return AjukanUsulan::itemSendiri($this->irban(), $id);
        } catch (ValidationException $e) {
            $this->usulanOpen = false;
            $this->dispatch('toast', type: 'error', message: AjukanUsulan::ITEM_BASI);
            $this->dispatch('usulan-fokus', id: null);

            return null;
        }
    }

    private function pkptDisahkan(): ?PkptModel
    {
        return PkptModel::where('tahun', (int) $this->tahun)->where('status', StatusPkpt::Disahkan->value)->first();
    }

    public function render(KesesuaianPkpt $kesesuaian, KalenderPkpt $kalender, WilayahPengawasan $wilayah, PerubahanPkpt $perubahan)
    {
        $user = $this->irban();
        if (! in_array($this->tampilan, self::TAMPILAN, true)) {
            $this->tampilan = 'kalender';
        }
        $pkpt = PkptModel::where('tahun', (int) $this->tahun)->first();
        $disahkan = $pkpt?->status === StatusPkpt::Disahkan;
        // Jenis and effective dates feed the table, the cards and the open usulan modal; the calendar gets them from KalenderPkpt.
        $perluBaris = $this->tampilan === 'tabel' || $this->usulanOpen;
        $items = $disahkan
            ? $pkpt->items()->aktif()->where('irban_id', $user->irban_id)->when($perluBaris, fn ($q) => $q->with('jenisPenugasan'))
                ->orderBy('tanggal_mulai')->orderBy('id')->get()
            : collect();
        $rentang = $perluBaris ? $items->mapWithKeys(fn (PkptItem $i) => [$i->id => $kesesuaian->rentang($i)]) : collect();
        $tahunList = PkptModel::pluck('tahun')->push(now('Asia/Jakarta')->year)
            ->unique()->sortDesc()->mapWithKeys(fn ($t) => [$t => $t])->all();

        $pakaiKalender = $this->tampilan === 'kalender' && $items->isNotEmpty();
        $usulan = $disahkan && $this->tampilan === 'usulan'
            ? PkptUsulan::where('pkpt_id', $pkpt->id)->where('irban_id', $user->irban_id)
                ->with(['item.jenisPenugasan', 'jenisPenugasan', 'perubahan:id,nomor_urut'])->latest()->orderByDesc('id')->get()
            : collect();

        return view('livewire.irban.pkpt', [
            'kalender' => $pakaiKalender ? $kalender->untuk((int) $this->tahun, $user->irban_id) : [],
            'wilayah' => $pakaiKalender ? $wilayah->untuk((int) $this->tahun, $user->irban_id)[0] ?? null : null,
            'pkpt' => $pkpt,
            'items' => $items,
            'rentang' => $rentang,
            'riwayat' => $this->tampilan === 'usulan' ? [] : $perubahan->riwayatItemUntuk($user, $items->pluck('id')->all()),
            'usulan' => $usulan,
            'tahunList' => $tahunList,
            'irbanNama' => $user->irban?->nama ?? 'irban ini',
            'aktif' => $this->usulanOpen && $this->usulanItemId ? $items->firstWhere('id', $this->usulanItemId) : null,
            'penginput' => $this->usulanOpen ? DiinputOleh::pilihan($user) : collect(),
            'jenisOptions' => $this->usulanOpen && ! $this->usulanItemId ? JenisPenugasan::orderBy('nama')->pluck('nama', 'id')->all() : [],
        ]);
    }
}
