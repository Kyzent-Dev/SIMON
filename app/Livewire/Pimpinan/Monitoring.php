<?php

namespace App\Livewire\Pimpinan;

use App\Enums\StatusPengajuan;
use App\Livewire\Concerns\MintaEkspor;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Spt;
use App\Services\MonitoringSpt;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Monitoring SPT (pimpinan.md "Monitoring table"): accepted SPT grouped per PKPT item, then "Di luar PKPT", 20 groups per page, read-only.
 * Rows, flags and filter validation come from App\Services\MonitoringSpt; exports go through App\Services\Ekspor ('monitoring').
 */
#[Title('Monitoring SPT')]
class Monitoring extends Component
{
    use MintaEkspor, WithPagination;

    public const GRUP_PER_HALAMAN = 20;

    private const FILTER = ['tahun', 'tw', 'irban', 'status', 'jenis', 'kesesuaian', 'penanda', 'penjelasan'];

    #[Url]
    public string $tahun = '';

    #[Url]
    public string $tw = '';

    #[Url]
    public string $irban = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $jenis = '';

    #[Url]
    public string $kesesuaian = '';

    /** Set from the dashboard stat cards only (shown as chips). */
    #[Url]
    public string $penanda = '';

    #[Url]
    public string $penjelasan = '';

    /** The pagination control binds here; fixed at 20 groups per page. */
    public int $perPage = self::GRUP_PER_HALAMAN;

    public function mount(): void
    {
        Gate::authorize('monitoring', Spt::class);
    }

    public function updated(string $prop): void
    {
        Gate::authorize('monitoring', Spt::class);
        if (in_array($prop, self::FILTER, true)) {
            $this->resetPage();
        }
    }

    public function aturUlang(): void
    {
        Gate::authorize('monitoring', Spt::class);
        foreach (array_diff(self::FILTER, ['tahun']) as $k) {
            $this->{$k} = '';
        }
        $this->resetPage();
    }

    public function ekspor(string $format)
    {
        Gate::authorize('monitoring', Spt::class);
        if (! in_array($format, ['xlsx', 'pdf'], true)) {
            return null;
        }

        return $this->mintaEkspor('monitoring', $format, $this->only(self::FILTER));
    }

    /** Validates on every render and writes the result back, so unknown URL values fall back visibly (selects show the default). */
    private function terapkan(): array
    {
        $f = app(MonitoringSpt::class)->filter($this->only(self::FILTER));
        foreach (self::FILTER as $k) {
            $this->{$k} = (string) $f[$k];
        }

        return $f;
    }

    public function render()
    {
        Gate::authorize('monitoring', Spt::class);
        $f = $this->terapkan();
        $grup = app(MonitoringSpt::class)->baris($f);
        $halaman = min(max(1, $this->getPage()), max(1, (int) ceil(count($grup) / self::GRUP_PER_HALAMAN)));
        $tahunList = Spt::where('status_pengajuan', StatusPengajuan::Diterima)->whereNotNull('tahun')->distinct()->pluck('tahun')
            ->push((int) now('Asia/Jakarta')->year, $f['tahun'])->unique()->sortDesc()->mapWithKeys(fn ($t) => [$t => (string) $t])->all();

        return view('livewire.pimpinan.monitoring', [
            'grup' => new LengthAwarePaginator(array_slice($grup, ($halaman - 1) * self::GRUP_PER_HALAMAN, self::GRUP_PER_HALAMAN), count($grup), self::GRUP_PER_HALAMAN, $halaman),
            'filterAktif' => count(array_filter(array_diff_key($f, ['tahun' => 0]))),
            'kunci' => md5(json_encode($f)),
            'tahunList' => $tahunList,
            'irbanOptions' => Irban::orderBy('nama')->pluck('nama', 'id')->all(),
            'jenisOptions' => JenisPenugasan::orderBy('nama')->pluck('nama', 'id')->all(),
        ]);
    }
}
