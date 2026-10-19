<?php

namespace App\Livewire\BagianUmum;

use App\Actions\Spt\MintaRevisi;
use App\Actions\Spt\TerimaPengajuan;
use App\Actions\Spt\TolakPengajuan;
use App\Enums\StatusPengajuan;
use App\Models\Irban;
use App\Models\Spt;
use App\Services\Penomoran;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kotak Masuk Pengajuan (design/dashboard/bagian-umum.md "Inbox"): status tabs + filters, list/detail with the selected SPT in the URL,
 * Terima / Perlu Revisi / Tolak through app/Actions/Spt. Only non-draf SPT are ever looked up.
 */
#[Title('Kotak Masuk Pengajuan')]
class Pengajuan extends Component
{
    public const TABS = ['menunggu_nomor' => 'Menunggu', 'perlu_revisi' => 'Perlu Revisi', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];

    /** shortcut: the list shows the first 100 of a tab; the register (Task 5) is the full view of numbered SPT. */
    public const BATAS = 100;

    #[Url]
    public string $tab = 'menunggu_nomor';

    #[Url]
    public string $irban = '';

    #[Url]
    public string $dari = '';

    #[Url]
    public string $sampai = '';

    #[Url]
    public ?int $pilih = null;

    public bool $showTerima = false;

    public bool $showCatatan = false;

    /** 'revisi' | 'tolak' for the catatan modal. */
    public string $mode = 'revisi';

    public string $catatan = '';

    /** Display only: Penomoran::pratinjau when the Terima modal opened; the issued number is always taken fresh. */
    public string $pratinjau = '';

    public bool $konflik = false;

    public function mount(): void
    {
        Gate::authorize('kelolaNomor', Spt::class);
    }

    public function updated(string $prop): void
    {
        Gate::authorize('kelolaNomor', Spt::class);
        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'menunggu_nomor';
        }
        if (in_array($prop, ['tab', 'irban', 'dari', 'sampai'], true)) {
            $this->konflik = false;
        }
    }

    public function pilihSpt(int $id): void
    {
        $this->spt($id);
        $this->pilih = $id;
        $this->konflik = false;
    }

    public function tutup(): void
    {
        $this->pilih = null;
        $this->konflik = false;
    }

    public function muatUlang(): void
    {
        Gate::authorize('kelolaNomor', Spt::class);
        $this->konflik = false;
    }

    public function bukaTerima(): void
    {
        if (! $spt = $this->menunggu()) {
            return;
        }
        $tanggal = CarbonImmutable::now('Asia/Jakarta');
        $this->pratinjau = app(Penomoran::class)->pratinjau($spt->irban, $tanggal, $spt->klasifikasi)['nomor_lengkap'];
        $this->showTerima = true;
    }

    public function terima(TerimaPengajuan $terima): void
    {
        $this->showTerima = false;
        $this->putuskan(function (Spt $spt) use ($terima) {
            $hasil = $terima->handle(auth()->user(), $spt);
            $this->dispatch('toast', type: 'success', message: "Nomor {$hasil->nomor_lengkap} diterbitkan");
        });
    }

    public function bukaCatatan(string $mode): void
    {
        if (! in_array($mode, ['revisi', 'tolak'], true) || ! $this->menunggu()) {
            return;
        }
        $this->mode = $mode;
        $this->catatan = '';
        $this->resetErrorBag();
        $this->showCatatan = true;
    }

    public function kirimCatatan(MintaRevisi $revisi, TolakPengajuan $tolak): void
    {
        $this->resetErrorBag();
        $this->putuskan(function (Spt $spt) use ($revisi, $tolak) {
            $tolakan = $this->mode === 'tolak';
            ($tolakan ? $tolak : $revisi)->handle(auth()->user(), $spt, $this->catatan);
            $this->showCatatan = false;
            $this->catatan = '';
            $this->dispatch('toast', type: 'success', message: $tolakan ? 'Pengajuan ditolak' : 'Pengajuan dikembalikan untuk direvisi');
        });
    }

    /** Runs a decision on the selected SPT; on success selects the next item in the current list order, on a lost race shows the conflict alert. */
    private function putuskan(callable $aksi): void
    {
        $spt = $this->spt($this->pilih);
        $urutan = $this->daftar()->pluck('id')->all();
        try {
            $aksi($spt);
        } catch (ValidationException $e) {
            if (! isset($e->errors()['spt'])) {
                throw $e;
            }
            $this->showTerima = $this->showCatatan = false;
            $this->konflik = true;

            return;
        }
        $posisi = array_search($spt->id, $urutan, true);
        $sisa = $this->daftar()->pluck('id')->all();
        $this->pilih = $sisa[min($posisi === false ? 0 : $posisi, max(count($sisa) - 1, 0))] ?? null;
        $this->dispatch('kotak-masuk-maju');
    }

    /** The selected SPT if it is still pending; otherwise the conflict alert. */
    private function menunggu(): ?Spt
    {
        $spt = $this->spt($this->pilih);
        if ($spt->status_pengajuan !== StatusPengajuan::MenungguNomor) {
            $this->konflik = true;

            return null;
        }

        return $spt;
    }

    /** Authorized, non-draf lookup; tampered or draf ids 404. */
    private function spt(?int $id): Spt
    {
        Gate::authorize('kelolaNomor', Spt::class);
        $spt = Spt::where('status_pengajuan', '!=', StatusPengajuan::Draf)->findOrFail($id ?? 0);
        Gate::authorize('view', $spt);

        return $spt;
    }

    private function filter(): Builder
    {
        $tanggal = fn (string $v) => CarbonImmutable::canBeCreatedFromFormat($v, 'Y-m-d') ? CarbonImmutable::createFromFormat('Y-m-d', $v, 'Asia/Jakarta') : null;
        $dari = $tanggal($this->dari)?->startOfDay();
        $sampai = $tanggal($this->sampai)?->endOfDay();

        return Spt::query()->where('status_pengajuan', '!=', StatusPengajuan::Draf)
            ->when(filter_var($this->irban, FILTER_VALIDATE_INT), fn ($q, $id) => $q->where('irban_id', $id))
            ->when($dari, fn ($q) => $q->where('diajukan_pada', '>=', $dari->utc()))
            ->when($sampai, fn ($q) => $q->where('diajukan_pada', '<=', $sampai->utc()));
    }

    private function daftar()
    {
        // The queue is oldest first; processed tabs show the latest first.
        $arah = $this->tab === 'menunggu_nomor' || $this->tab === 'perlu_revisi' ? 'asc' : 'desc';

        return $this->filter()->with(['irban', 'jenisPenugasan'])->where('status_pengajuan', $this->tab)
            ->orderBy('diajukan_pada', $arah)->orderBy('id', $arah)->limit(self::BATAS)->get();
    }

    public function render()
    {
        Gate::authorize('kelolaNomor', Spt::class);
        $jumlah = $this->filter()->selectRaw('status_pengajuan, count(*) as n')->groupBy('status_pengajuan')->pluck('n', 'status_pengajuan');
        $detail = $this->pilih ? Spt::where('status_pengajuan', '!=', StatusPengajuan::Draf)->with([
            'irban', 'klasifikasi', 'jenisPenugasan', 'pkptItem.pkpt', 'pkptItem.jenisPenugasan', 'ppj', 'objek.parent', 'personel.pegawai', 'dokumen',
            'riwayat' => fn ($q) => $q->with('olehUser')->latest('id'),
        ])->find($this->pilih) : null;

        return view('livewire.bagian-umum.pengajuan', [
            'daftar' => $this->daftar(),
            'tabs' => collect(self::TABS)->map(fn ($label, $k) => $label.' ('.($jumlah[$k] ?? 0).')')->all(),
            'jumlahTab' => (int) ($jumlah[$this->tab] ?? 0),
            'irbanOptions' => Irban::orderBy('nama')->pluck('nama', 'id')->all(),
            'detail' => $detail,
        ]);
    }
}
