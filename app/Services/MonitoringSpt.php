<?php

namespace App\Services;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Spt;

/** Monitoring table (PRD §7.9): accepted SPT grouped per PKPT item, then "Di luar PKPT". Shared by the page, export and dashboard cards. */
class MonitoringSpt
{
    /** `berjalan` = On Process group, `selesai`, or one On Process status. */
    public const STATUS = ['berjalan', 'selesai', 'siap_dilaksanakan', 'pelaksanaan', 'penyusunan_laporan'];

    public const KESESUAIAN = ['sesuai', 'tidak_sesuai', 'non_pkpt'];

    public function __construct(private Triwulan $triwulan, private PenandaSpt $penanda, private KesesuaianPkpt $kesesuaian) {}

    /**
     * Validated filter; unknown or malformed values fall back to the default (tahun = current Jakarta year, others null).
     *
     * @return array{tahun: int, tw: ?int, irban: ?int, status: ?string, jenis: ?int, kesesuaian: ?string, penanda: ?string, penjelasan: ?string}
     */
    public function filter(array $input): array
    {
        $int = fn ($v) => is_int($v) || (is_string($v) && ctype_digit($v)) ? (int) $v : null;
        $pilih = fn ($v, array $boleh) => is_string($v) && in_array($v, $boleh, true) ? $v : null;
        $tahun = $int($input['tahun'] ?? null);
        $tw = $int($input['tw'] ?? null);
        $irban = $int($input['irban'] ?? null);
        $jenis = $int($input['jenis'] ?? null);

        return [
            'tahun' => $tahun !== null && $tahun >= 2000 && $tahun <= 2100 ? $tahun : (int) now('Asia/Jakarta')->year,
            'tw' => $tw !== null && $tw >= 1 && $tw <= 4 ? $tw : null,
            'irban' => $irban && Irban::whereKey($irban)->exists() ? $irban : null,
            'status' => $pilih($input['status'] ?? null, self::STATUS),
            'jenis' => $jenis && JenisPenugasan::whereKey($jenis)->exists() ? $jenis : null,
            'kesesuaian' => $pilih($input['kesesuaian'] ?? null, self::KESESUAIAN),
            'penanda' => $pilih($input['penanda'] ?? null, ['terlambat']),
            'penjelasan' => $pilih($input['penjelasan'] ?? null, ['terbuka']),
        ];
    }

    /**
     * Groups (items by effective start, then id; "Di luar PKPT" last; only groups with rows):
     *   {item_id: ?int, label: string, jenis: ?{id, nama, warna, warna_teks}, mulai: ?CarbonImmutable, selesai: ?CarbonImmutable, irban: ?string, spt: list<row>}
     * Row: {id, nomor_lengkap, irban: string, jenis: ?{id, nama, warna, warna_teks}, mulai, selesai, kesesuaian: sesuai|tidak_sesuai|non_pkpt,
     *   status: StatusSpt, penanda: list<string>, kendala: ?{kategori, uraian}, url}
     */
    public function baris(array $filter): array
    {
        $grup = [];
        foreach ($this->spt($this->filter($filter)) as [$spt, $baris]) {
            $item = $spt->pkptItem;
            $key = $item?->id ?? 0;
            if (! isset($grup[$key])) {
                [$mulai, $selesai] = $item ? $this->kesesuaian->rentang($item) : [null, null];
                $grup[$key] = [
                    'item_id' => $item?->id,
                    'label' => $item ? $item->jenisPenugasan->nama : 'Di luar PKPT',
                    'jenis' => $this->jenis($item?->jenisPenugasan),
                    'mulai' => $mulai,
                    'selesai' => $selesai,
                    'irban' => $item?->irban->nama,
                    'spt' => [],
                ];
            }
            $grup[$key]['spt'][] = $baris;
        }

        uasort($grup, fn ($a, $b) => [$a['item_id'] === null, $a['mulai'], $a['item_id']] <=> [$b['item_id'] === null, $b['mulai'], $b['item_id']]);

        return array_values($grup);
    }

    /** Number of SPT rows baris() returns for this filter (dashboard stat cards), through the same filtering code. */
    public function jumlah(array $filter): int
    {
        return count($this->spt($this->filter($filter)));
    }

    /** @return list<array{0: Spt, 1: array}> SPT in scope after every filter, ordered by tanggal_mulai then id */
    private function spt(array $f): array
    {
        $q = Spt::with(['irban:id,nama', 'jenisPenugasan', 'kendala', 'pkptItem.revisiDisetujui', 'pkptItem.irban:id,nama', 'pkptItem.jenisPenugasan'])
            ->where('status_pengajuan', StatusPengajuan::Diterima)
            ->where('tahun', $f['tahun'])
            ->when($f['tw'], fn ($q, $tw) => $this->triwulan->batasi($q, $f['tahun'], $tw))
            ->when($f['irban'], fn ($q, $id) => $q->where('irban_id', $id))
            ->when($f['jenis'], fn ($q, $id) => $q->where('jenis_penugasan_id', $id))
            ->when($f['kesesuaian'] === 'non_pkpt', fn ($q) => $q->whereNull('pkpt_item_id'))
            ->when($f['penjelasan'], fn ($q) => $q->whereHas('komentar', fn ($k) => $k->permintaanTerbuka()))
            ->when($f['status'], fn ($q, $s) => $s === 'berjalan' ? $q->whereNull('ditandai_selesai_pada') : $q->berstatus(StatusSpt::from($s)))
            ->orderBy('tanggal_mulai')->orderBy('id');

        $spts = $q->get();
        $penanda = $this->penanda->untukBanyak($spts);
        $hasil = [];
        foreach ($spts as $spt) {
            $flags = $penanda[$spt->id];
            $kesesuaian = match (true) {
                $spt->pkpt_item_id === null => 'non_pkpt',
                in_array('tidak_sesuai_pkpt', $flags, true) => 'tidak_sesuai',
                default => 'sesuai',
            };
            if (($f['kesesuaian'] && $f['kesesuaian'] !== $kesesuaian) || ($f['penanda'] && ! in_array($f['penanda'], $flags, true))) {
                continue;
            }
            $hasil[] = [$spt, [
                'id' => $spt->id,
                'nomor_lengkap' => $spt->nomor_lengkap,
                'irban' => $spt->irban->nama,
                'jenis' => $this->jenis($spt->jenisPenugasan),
                'mulai' => $spt->tanggal_mulai,
                'selesai' => $spt->tanggal_selesai,
                'kesesuaian' => $kesesuaian,
                'status' => $spt->status(),
                'penanda' => $flags,
                'kendala' => $spt->kendala ? ['kategori' => $spt->kendala->kategori, 'uraian' => $spt->kendala->uraian] : null,
                'url' => route('spt.show', $spt->id),
            ]];
        }

        return $hasil;
    }

    private function jenis(?JenisPenugasan $j): ?array
    {
        return $j ? ['id' => $j->id, 'nama' => $j->nama, 'warna' => $j->warna, 'warna_teks' => $j->warna_teks] : null;
    }
}
