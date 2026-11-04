<?php

namespace App\Notifications;

use Carbon\CarbonImmutable;

class RangkumanNomorTerbit extends NotifikasiSintas
{
    /** @param array{tanggal: string, jumlah: int, per_irban: array<string, int>} $hasil App\Services\RangkumanNomorTerbit::hitung(); scalars only, safe for the queue */
    public function __construct(protected array $hasil) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database']; // bell only (K2): no email for the daily summary
    }

    public function jenis(): string
    {
        return 'rangkuman_nomor_terbit';
    }

    public function judul(): string
    {
        return "{$this->hasil['jumlah']} nomor SPT terbit";
    }

    public function konteks(): ?string
    {
        $perIrban = collect($this->hasil['per_irban'])->map(fn ($n, $nama) => "{$nama}: {$n}")->implode(', ');

        return CarbonImmutable::parse($this->hasil['tanggal'])->translatedFormat('d F Y').' · '.$perIrban;
    }

    public function url(): string
    {
        return route('pimpinan.monitoring', ['tahun' => CarbonImmutable::parse($this->hasil['tanggal'])->year, 'tampilan' => 'spt', 'urut' => 'terbit'], absolute: false);
    }

    public function isi(): array
    {
        return [$this->judul(), (string) $this->konteks()];
    }
}
