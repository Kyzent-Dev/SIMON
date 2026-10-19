<?php

namespace App\Services;

use App\Models\Irban;
use App\Models\Klasifikasi;
use App\Models\NomorCounter;
use Carbon\CarbonImmutable;

/** SPT numbering (PRD §9.1): one locked counter row per register, never MAX(nomor_urut)+1; numbers are never reused. */
class Penomoran
{
    public function __construct(private Settings $settings) {}

    /** @return array{tahun: int, scope: string} counter key for a register (year of the decision). */
    public function kunci(?Irban $irban, int $tahun): array
    {
        return [
            'tahun' => $this->settings->get('penomoran.reset_tahunan') ? $tahun : 0,
            'scope' => $this->settings->get('penomoran.scope') === 'per_irban' ? "irban:{$irban?->id}" : 'global',
        ];
    }

    /**
     * Takes the next number. Must run inside the caller's DB::transaction.
     *
     * @return array{counter: NomorCounter, nomor_urut: int, nomor_lengkap: string}
     */
    public function ambil(Irban $irban, CarbonImmutable $tanggal, ?Klasifikasi $klasifikasi = null): array
    {
        $kunci = $this->kunci($irban, $this->tahun($tanggal));

        // firstOrCreate falls back to createOrFirst, which survives the unique race on a register's first number.
        // On SQLite the IMMEDIATE transaction mode (config/database.php) already holds the write lock; lockForUpdate is for MySQL.
        $id = NomorCounter::firstOrCreate($kunci)->id;
        $counter = NomorCounter::whereKey($id)->lockForUpdate()->firstOrFail();
        $counter->increment('nomor_terakhir');

        return [
            'counter' => $counter,
            'nomor_urut' => $counter->nomor_terakhir,
            'nomor_lengkap' => $this->format($counter->nomor_terakhir, $irban, $tanggal, $klasifikasi),
        ];
    }

    /** @return array{nomor_urut: int, nomor_lengkap: string} what the next ambil() would give; no lock, no write. */
    public function pratinjau(Irban $irban, CarbonImmutable $tanggal, ?Klasifikasi $klasifikasi = null): array
    {
        $nomor = (int) NomorCounter::where($this->kunci($irban, $this->tahun($tanggal)))->value('nomor_terakhir') + 1;

        return ['nomor_urut' => $nomor, 'nomor_lengkap' => $this->format($nomor, $irban, $tanggal, $klasifikasi)];
    }

    public function format(int $nomorUrut, Irban $irban, CarbonImmutable $tanggal, ?Klasifikasi $klasifikasi = null): string
    {
        $template = $this->settings->get('penomoran.format');
        if ($klasifikasi && $this->settings->get('penomoran.klasifikasi_di_nomor')) {
            $template = str_replace('{klasifikasi}', str_replace(' ', '-', mb_strtoupper($klasifikasi->nama)), $template);
        } else {
            $template = str_contains($template, '/{klasifikasi}') ? str_replace('/{klasifikasi}', '', $template)
                : str_replace(['{klasifikasi}/', '{klasifikasi}'], '', $template);
        }

        return strtr($template, [
            '{kode_arsip}' => $this->settings->get('penomoran.kode_arsip'),
            '{nomor_urut}' => (string) $nomorUrut,
            '{kode_irban}' => $irban->kode_nomor,
            '{tahun}' => (string) $this->tahun($tanggal),
        ]);
    }

    private function tahun(CarbonImmutable $tanggal): int
    {
        return $tanggal->setTimezone('Asia/Jakarta')->year;
    }
}
