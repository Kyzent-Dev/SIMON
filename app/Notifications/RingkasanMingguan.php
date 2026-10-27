<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;

class RingkasanMingguan extends NotifikasiSintas
{
    /** @param array $hasil App\Services\RingkasanMingguan::hitung(); awal/akhir are kept as Y-m-d strings so the queue payload holds scalars only */
    public function __construct(private array $hasil)
    {
        $this->hasil['awal'] = CarbonImmutable::parse($hasil['awal'])->toDateString();
        $this->hasil['akhir'] = CarbonImmutable::parse($hasil['akhir'])->toDateString();
    }

    public function jenis(): string
    {
        return 'ringkasan_mingguan';
    }

    public function judul(): string
    {
        return 'Ringkasan mingguan SINTAS';
    }

    public function konteks(): ?string
    {
        return CarbonImmutable::parse($this->hasil['awal'])->translatedFormat('d F Y').' – '.CarbonImmutable::parse($this->hasil['akhir'])->translatedFormat('d F Y');
    }

    public function url(): string
    {
        return route('pimpinan.dashboard', absolute: false);
    }

    public function isi(): array
    {
        $h = $this->hasil;
        $perubahan = $h['perubahan'] ?? $h['revisi'] ?? 0; // a job queued before the rename still carries `revisi`
        $baris = ["Nomor SPT terbit: {$h['nomor']}", "SPT ditandai Selesai: {$h['selesai']}", "Pengajuan menunggu nomor: {$h['menunggu_nomor']}",
            "Perubahan PKPT menunggu persetujuan: {$perubahan}", "SPT terlambat: {$h['terlambat']}", ...$h['daftar']];
        if ($h['lainnya'] > 0) {
            $baris[] = "dan {$h['lainnya']} lainnya.";
        }

        return $baris;
    }
}
