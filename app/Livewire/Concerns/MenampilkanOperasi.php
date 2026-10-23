<?php

namespace App\Livewire\Concerns;

use App\Enums\AksiPerubahan;
use App\Models\PkptPerubahanItem;
use App\Services\PerubahanPkpt as AturanPerubahan;
use App\Support\Tanggal;
use Carbon\CarbonImmutable;

/** Display rows of a Perubahan PKPT operation, shared by the Admin and Pimpinan pages (rendered by x-app.operasi-perubahan). */
trait MenampilkanOperasi
{
    /**
     * Display rows of one operation: item chip source and the changed fields before → after.
     * Before = data_lama once applied, else the item's current values; tambah has no before, hapus no after.
     *
     * @return array{op: PkptPerubahanItem, chip: array, baris: list<array{label: string, sebelum: ?string, sesudah: ?string}>}
     */
    protected function tampilOperasi(PkptPerubahanItem $op, array $irban, $jenis): array
    {
        $sebelum = $op->aksi === AksiPerubahan::Tambah ? null : ($op->data_lama ?? ($op->item ? AturanPerubahan::data($op->item) : null));
        $sesudah = $op->aksi === AksiPerubahan::Hapus ? null : $op->data_baru;
        $teks = function (?array $d) use ($irban, $jenis): ?array {
            if (! $d) {
                return null;
            }
            $tgl = filled($d['tanggal_mulai'] ?? null) && filled($d['tanggal_selesai'] ?? null)
                ? Tanggal::rentang(CarbonImmutable::parse($d['tanggal_mulai']), CarbonImmutable::parse($d['tanggal_selesai'])) : '—';

            return [
                'Irban' => $irban[$d['irban_id'] ?? 0] ?? '—',
                'Jenis penugasan' => $jenis[$d['jenis_penugasan_id'] ?? 0]->nama ?? '—',
                'Jadwal' => $tgl,
                'Keterangan' => filled($d['keterangan'] ?? null) ? $d['keterangan'] : '—',
                'Target SPT' => filled($d['target_spt'] ?? null) ? (string) $d['target_spt'] : '—',
            ];
        };
        [$a, $b] = [$teks($sebelum), $teks($sesudah)];
        $baris = collect($a ?? $b)->keys()
            ->filter(fn ($k) => $op->aksi !== AksiPerubahan::Ubah || $a[$k] !== $b[$k])
            ->map(fn ($k) => ['label' => $k, 'sebelum' => $a[$k] ?? null, 'sesudah' => $b[$k] ?? null])->values()->all();
        $sumber = $sebelum ?? $sesudah ?? [];

        return [
            'op' => $op,
            'chip' => [
                'jenis' => $jenis[$sumber['jenis_penugasan_id'] ?? 0] ?? null,
                'irban' => $irban[$sumber['irban_id'] ?? 0] ?? null,
                'jadwal' => ($a ?? $b)['Jadwal'] ?? null,
            ],
            'baris' => $baris,
        ];
    }
}
