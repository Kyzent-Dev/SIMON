<?php

namespace App\Services\Publik;

use App\Enums\JenisObjek;
use App\Enums\StatusPengajuan;
use App\Enums\StatusPkpt;
use App\Models\PkptItem;
use App\Services\Ekspor\PembangunRekap;
use App\Services\RekapTriwulan;
use App\Services\Settings;
use App\Services\Triwulan;
use App\Support\CachePublik;
use App\Support\Tanggal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

/**
 * The only door from the public pages to SPT data (PRD §9.6). Query builder with explicit columns, plain arrays out.
 * Filter keys: tahun int, tw 1–4|null, bulan 1–12|null, irban id|null, wilayah kecamatan objek id|null, jenis id|null.
 */
class RealisasiPublik
{
    public const PER_HALAMAN = 25;

    public function __construct(private Triwulan $triwulan, private Settings $settings, private RekapTriwulan $rekap) {}

    /**
     * Public SPT (one rule): klasifikasi tampil_publik, diterima + marked Selesai, with a PKPT item whose PKPT is Disahkan,
     * with a basis date; narrowed by the filters (tahun/tw/bulan by the Triwulan basis date). Selects nothing.
     */
    public function basis(array $f = []): Builder
    {
        $q = DB::table('spt')
            ->join('klasifikasi', 'klasifikasi.id', '=', 'spt.klasifikasi_id')
            ->join('pkpt_item', 'pkpt_item.id', '=', 'spt.pkpt_item_id')
            ->join('pkpt', 'pkpt.id', '=', 'pkpt_item.pkpt_id')
            ->where('klasifikasi.tampil_publik', true)
            ->where('spt.status_pengajuan', StatusPengajuan::Diterima->value)
            ->whereNotNull('spt.ditandai_selesai_pada')
            ->where('pkpt.status', StatusPkpt::Disahkan->value)
            ->whereNotNull(DB::raw($this->triwulan->kolomDasar()));

        if (isset($f['tahun'])) {
            $this->triwulan->batasi($q, $f['tahun'], $f['tw'] ?? null, $f['bulan'] ?? null);
        }
        if ($irban = $f['irban'] ?? null) {
            $q->where('spt.irban_id', $irban);
        }
        if ($jenis = $f['jenis'] ?? null) {
            $q->where('spt.jenis_penugasan_id', $jenis);
        }
        if ($wilayah = $f['wilayah'] ?? null) {
            $q->whereExists(fn ($s) => $s->selectRaw('1')->from('spt_objek')->join('objek', 'objek.id', '=', 'spt_objek.objek_id')
                ->whereColumn('spt_objek.spt_id', 'spt.id')
                ->where(fn ($w) => $w->where('objek.id', $wilayah)->orWhere('objek.parent_id', $wilayah)));
        }

        return $q;
    }

    /** @return array{selesai: int, item: int, rencana: int, persen: ?float} */
    public function ringkasan(array $f): array
    {
        return CachePublik::ingat('ringkasan', $f, fn () => $this->hitungRingkasan($f));
    }

    private function hitungRingkasan(array $f): array
    {
        $item = $this->itemDisahkan($f['tahun'], $f['irban'] ?? null, $f['jenis'] ?? null);
        $selesai = $this->basis($f)->count();
        $rencana = array_sum($this->rencanaPerIrban($item, $f['tahun'], $f['tw'] ?? null));

        return ['selesai' => $selesai, 'item' => $item->count(), 'rencana' => $rencana, 'persen' => $rencana > 0 ? round($selesai / $rencana * 100, 1) : null];
    }

    /** @return list<int>|list<array{irban: string, jumlah: list<int>}> 12 counts Jan–Des, or one series per irban (by name) */
    public function perBulan(array $f, bool $perIrban): array
    {
        return CachePublik::ingat('perBulan', [$f, $perIrban], fn () => $this->hitungPerBulan($f, $perIrban));
    }

    private function hitungPerBulan(array $f, bool $perIrban): array
    {
        $bulan = 'substr('.$this->triwulan->kolomDasar().', 6, 2)';
        $rows = $this->basis($f)->selectRaw("$bulan as bulan, spt.irban_id, count(*) as jumlah")->groupByRaw("$bulan, spt.irban_id")->get();
        $seri = [];
        foreach ($rows as $r) {
            $seri[$r->irban_id] ??= array_fill(0, 12, 0);
            $seri[$r->irban_id][(int) $r->bulan - 1] += (int) $r->jumlah;
        }
        if (! $perIrban) {
            $total = array_fill(0, 12, 0);
            foreach ($seri as $s) {
                foreach ($s as $i => $n) {
                    $total[$i] += $n;
                }
            }

            return $total;
        }

        return $this->irban($f['irban'] ?? null)
            ->map(fn ($i) => ['irban' => $i->nama, 'jumlah' => $seri[$i->id] ?? array_fill(0, 12, 0)])->all();
    }

    /**
     * Slices by count desc (then label), at most 6, the rest summed into `Lainnya` (when > 0).
     * `warna`: jenis_penugasan.warna, or the irban's index among all irban by name; null for Lainnya.
     *
     * @return list<array{label: string, jumlah: int, persen: float, warna: string|int|null}>
     */
    public function proporsi(array $f, string $menurut): array
    {
        return CachePublik::ingat('proporsi', [$f, $menurut], fn () => $this->hitungProporsi($f, $menurut));
    }

    private function hitungProporsi(array $f, string $menurut): array
    {
        $jenis = $menurut === 'jenis';
        $kolom = $jenis ? 'spt.jenis_penugasan_id' : 'spt.irban_id';
        $jumlah = $this->basis($f)->selectRaw("$kolom as id, count(*) as jumlah")->groupBy($kolom)->pluck('jumlah', 'id');
        $total = (int) $jumlah->sum();
        if ($total === 0) {
            return [];
        }
        $label = $jenis
            ? DB::table('jenis_penugasan')->get(['id', 'nama', 'warna'])->keyBy('id')->map(fn ($j) => [$j->nama, $j->warna])
            : $this->irban(null)->values()->mapWithKeys(fn ($i, $n) => [$i->id => [$i->nama, $n]]);

        $irisan = $jumlah->map(fn ($n, $id) => ['label' => $label[$id][0] ?? 'Tanpa jenis penugasan', 'jumlah' => (int) $n, 'warna' => $label[$id][1] ?? null])
            ->sortBy([['jumlah', 'desc'], ['label', 'asc']])->values();
        $sisa = $irisan->slice(6)->sum('jumlah');
        $irisan = $irisan->take(6);
        if ($sisa > 0) {
            $irisan->push(['label' => 'Lainnya', 'jumlah' => $sisa, 'warna' => null]);
        }

        return $irisan->map(fn ($s) => ['label' => $s['label'], 'jumlah' => $s['jumlah'], 'persen' => round($s['jumlah'] / $total * 100, 1), 'warna' => $s['warna']])->all();
    }

    /**
     * Pimpinan chart shape (grafikRealisasi), one entry per irban by name. Follows tahun/irban/jenis and $tw (null = Setahun);
     * bulan and wilayah are ignored (rencana has neither).
     *
     * @return array{periode: string, label: list<string>, rencana: list<int>, realisasi: list<int>, persen: list<?int>}
     */
    public function rencanaVsRealisasi(array $f, ?int $tw): array
    {
        return CachePublik::ingat('rencanaVsRealisasi', [$f, $tw], fn () => $this->hitungRencanaVsRealisasi($f, $tw));
    }

    private function hitungRencanaVsRealisasi(array $f, ?int $tw): array
    {
        $f = ['tahun' => $f['tahun'], 'tw' => $tw, 'irban' => $f['irban'] ?? null, 'jenis' => $f['jenis'] ?? null];
        $rencana = $this->rencanaPerIrban($this->itemDisahkan($f['tahun'], $f['irban'], $f['jenis']), $f['tahun'], $tw);
        $realisasi = $this->basis($f)->selectRaw('spt.irban_id, count(*) as jumlah')->groupBy('spt.irban_id')->pluck('jumlah', 'irban_id');

        $g = ['periode' => $tw ? PembangunRekap::TW[$tw] : 'Setahun', 'label' => [], 'rencana' => [], 'realisasi' => [], 'persen' => []];
        foreach ($this->irban($f['irban']) as $i) {
            $r = $rencana[$i->id] ?? 0;
            $s = (int) ($realisasi[$i->id] ?? 0);
            $g['label'][] = $i->nama;
            $g['rencana'][] = $r;
            $g['realisasi'][] = $s;
            $g['persen'][] = $r > 0 ? (int) round($s / $r * 100) : null; // integer, as the Pimpinan chart (RekapTriwulan)
        }

        return $g;
    }

    /** Rows: [nomor (only when publik.tampilkan_nomor_spt),] jenis {nama, warna, warna_teks}, irban, wilayah, tanggal, triwulan. */
    public function tabel(array $f, string $urut, int $halaman): LengthAwarePaginator
    {
        $arah = $urut === 'asc' ? 'asc' : 'desc';
        $kolom = $this->triwulan->kolomDasar();
        $nomor = (bool) $this->settings->get('publik.tampilkan_nomor_spt');

        $hasil = $this->basis($f)
            ->join('irban', 'irban.id', '=', 'spt.irban_id')
            ->leftJoin('jenis_penugasan', 'jenis_penugasan.id', '=', 'spt.jenis_penugasan_id')
            ->select(['spt.id', 'irban.nama as irban', 'jenis_penugasan.nama as jenis', 'jenis_penugasan.warna', 'jenis_penugasan.warna_teks',
                'spt.tanggal_mulai', 'spt.tanggal_selesai'])
            ->selectRaw("$kolom as dasar")
            ->when($nomor, fn ($q) => $q->addSelect('spt.nomor_lengkap'))
            ->orderByRaw("$kolom $arah")->orderBy('spt.id', $arah)
            ->paginate(self::PER_HALAMAN, ['*'], 'page', max(1, $halaman));

        $wilayah = $this->labelWilayah(
            $this->objekSpt()->whereIn('spt_objek.spt_id', $hasil->pluck('id'))->addSelect('spt_objek.spt_id as kunci')->get(),
            (bool) $this->settings->get('publik.tampilkan_nama_objek'),
        );

        return $hasil->through(fn ($r) => [
            ...($nomor ? ['nomor' => $r->nomor_lengkap] : []),
            'jenis' => ['nama' => $r->jenis, 'warna' => $r->warna, 'warna_teks' => $r->warna_teks],
            'irban' => $r->irban,
            'wilayah' => $wilayah[$r->id] ?? JadwalPublik::BELUM_DITENTUKAN,
            'tanggal' => Tanggal::rentang(CarbonImmutable::parse($r->tanggal_mulai), CarbonImmutable::parse($r->tanggal_selesai)),
            'triwulan' => PembangunRekap::TW[$this->triwulan->dari(CarbonImmutable::parse($r->dasar))],
        ]);
    }

    /** @return array{tahun: list<int>, tahunDefault: int, irban: list<array>, wilayah: list<array>, jenis: list<array>} */
    public function opsi(): array
    {
        return CachePublik::ingat('opsi', [], fn () => $this->hitungOpsi());
    }

    private function hitungOpsi(): array
    {
        $sekarang = now()->year;
        $tahun = DB::table('pkpt')->where('status', StatusPkpt::Disahkan->value)->orderByDesc('tahun')->pluck('tahun')->map(fn ($t) => (int) $t)->all();

        return [
            'tahun' => $tahun ?: [$sekarang],
            'tahunDefault' => in_array($sekarang, $tahun, true) ? $sekarang : ($tahun[0] ?? $sekarang),
            // false: `tahun` is only the current-year fallback (no Disahkan PKPT at all).
            'adaDisahkan' => $tahun !== [],
            'irban' => $this->irban(null)->map(fn ($i) => ['id' => $i->id, 'nama' => $i->nama])->all(),
            'wilayah' => DB::table('objek')->where('jenis', JenisObjek::Kecamatan->value)->orderBy('nama')->get(['id', 'nama'])->map(fn ($o) => (array) $o)->all(),
            'jenis' => DB::table('jenis_penugasan')->orderBy('nama')->get(['id', 'nama', 'warna', 'warna_teks'])->map(fn ($j) => (array) $j)->all(),
        ];
    }

    /** Active items of the year's Disahkan PKPT (internal: models only so KesesuaianPkpt and the snapshot rules apply; never returned). */
    public function itemDisahkan(int $tahun, ?int $irban = null, ?int $jenis = null): Collection
    {
        return PkptItem::query()
            ->join('pkpt', 'pkpt.id', '=', 'pkpt_item.pkpt_id')
            ->where('pkpt.tahun', $tahun)->where('pkpt.status', StatusPkpt::Disahkan->value)
            ->when($irban, fn ($q) => $q->where('pkpt_item.irban_id', $irban))
            ->when($jenis, fn ($q) => $q->where('pkpt_item.jenis_penugasan_id', $jenis))
            ->aktif()
            ->get(['pkpt_item.id', 'pkpt_item.irban_id', 'pkpt_item.jenis_penugasan_id', 'pkpt_item.tanggal_mulai', 'pkpt_item.tanggal_selesai',
                'pkpt_item.tanggal_mulai_disahkan', 'pkpt_item.tanggal_selesai_disahkan', 'pkpt_item.ditambahkan_perubahan_id', 'pkpt_item.target_spt', 'pkpt_item.keterangan']);
    }

    /** spt_objek → objek (+ parent): nama, jenis, induk; callers add the key column `kunci` and their own constraints. */
    public function objekSpt(): Builder
    {
        return DB::table('spt_objek')
            ->join('objek', 'objek.id', '=', 'spt_objek.objek_id')
            ->leftJoin('objek as induk', 'induk.id', '=', 'objek.parent_id')
            ->select(['objek.nama', 'objek.jenis', 'induk.nama as induk']);
    }

    /**
     * Wilayah label per key: kecamatan-level objects by their own name, desa/sekolah/puskesmas/bumdes rolled up to their
     * kecamatan; all own names when $namaObjek. Distinct, sorted, joined with ", ".
     *
     * @return array<int, string>
     */
    public function labelWilayah(iterable $rows, bool $namaObjek): array
    {
        $anak = array_map(fn ($j) => $j->value, JenisObjek::childTypes());
        $per = [];
        foreach ($rows as $r) {
            $nama = $namaObjek || ! in_array($r->jenis, $anak, true) ? $r->nama : $r->induk;
            if ($nama !== null) {
                $per[$r->kunci][$nama] = true;
            }
        }

        return array_map(function ($nama) {
            $nama = array_keys($nama);
            sort($nama, SORT_NATURAL | SORT_FLAG_CASE);

            return implode(', ', $nama);
        }, $per);
    }

    /** @return array<int, int> irban_id => planned SPT (RekapTriwulan::rencanaItem) of items in $tahun (and $tw) */
    private function rencanaPerIrban(Collection $item, int $tahun, ?int $tw): array
    {
        $rencana = [];
        foreach ($item as $i) {
            [$itemTw, $target] = $this->rekap->rencanaItem($i, $tahun) ?? [null, 0];
            if ($itemTw !== null && ($tw === null || $itemTw === $tw)) {
                $rencana[$i->irban_id] = ($rencana[$i->irban_id] ?? 0) + $target;
            }
        }

        return $rencana;
    }

    private function irban(?int $id): SupportCollection
    {
        return DB::table('irban')->when($id, fn ($q) => $q->where('id', $id))->orderBy('nama')->get(['id', 'nama']);
    }
}
