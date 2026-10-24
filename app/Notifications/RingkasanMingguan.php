<?php

namespace App\Notifications;

class RingkasanMingguan extends NotifikasiSimon
{
    private string $konteks;

    /** @param array $hasil App\Services\RingkasanMingguan::hitung() */
    public function __construct(private array $hasil)
    {
        $this->konteks = $hasil['awal']->translatedFormat('d F Y').' – '.$hasil['akhir']->translatedFormat('d F Y');
    }

    public function jenis(): string
    {
        return 'ringkasan_mingguan';
    }

    public function judul(): string
    {
        return 'Ringkasan mingguan SIMON-SPT';
    }

    public function konteks(): ?string
    {
        return $this->konteks;
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
