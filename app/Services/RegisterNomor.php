<?php

namespace App\Services;

use App\Enums\KejadianSpt;
use App\Enums\StatusSpt;
use App\Enums\SumberSpt;
use App\Models\Irban;
use App\Models\NomorCounter;
use App\Models\Spt;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/** Register Nomor SPT (digital stambook): the one query + row format shared by the table, the Excel export and the PDF. */
class RegisterNomor
{
    public const KOLOM = ['No. Urut', 'Nomor SPT', 'Tanggal', 'Irban', 'Perihal/Jenis', 'Status', 'Keterangan'];

    /** @param array{tahun: int, cari?: string, status?: string, irban?: int|string, bulan?: int|string} $f */
    public function query(array $f): Builder
    {
        $status = StatusSpt::tryFrom((string) ($f['status'] ?? ''));
        $irban = filter_var($f['irban'] ?? null, FILTER_VALIDATE_INT);
        $bulan = $this->bulan($f);
        $cari = trim((string) ($f['cari'] ?? ''));

        return Spt::query()->whereNotNull('nomor_urut')->where('tahun', (int) $f['tahun'])
            ->with(['irban', 'jenisPenugasan', 'riwayat' => fn ($q) => $q->where('kejadian', KejadianSpt::DIBATALKAN)->latest('id')])
            ->when($cari !== '', fn ($q) => $q->where('nomor_lengkap', 'like', '%'.addcslashes($cari, '%_\\').'%'))
            ->when($status, fn ($q) => $q->berstatus($status))
            ->when($irban, fn ($q) => $q->where('irban_id', $irban))
            ->when($bulan, fn ($q) => $q->whereMonth('tanggal_spt', $bulan))
            ->orderBy('nomor_urut')->orderBy('irban_id')->orderBy('id');
    }

    /** @return list<string> the cells of one row, identical for the table and both exports. */
    public function baris(Spt $spt): array
    {
        $ket = array_filter([
            $spt->sumber === SumberSpt::Sistem ? null : $spt->sumber->label(),
            ($alasan = $spt->riwayat->first()?->alasan) ? "Dibatalkan: {$alasan}" : null,
        ]);

        return [
            (string) $spt->nomor_urut, $spt->nomor_lengkap, $spt->tanggal_spt?->translatedFormat('d F Y') ?? '', $spt->irban->nama,
            $spt->jenisPenugasan?->nama ?? Str::limit((string) $spt->uraian_untuk, 80), $spt->status()->label(), implode('. ', $ket),
        ];
    }

    /** @return list<list<string>> */
    public function semua(array $f): array
    {
        return $this->query($f)->get()->map(fn (Spt $s) => $this->baris($s))->all();
    }

    /** @return list<string> human summary of the active filters (PDF header). */
    public function ringkasanFilter(array $f): array
    {
        $bulan = $this->bulan($f);
        $irban = filter_var($f['irban'] ?? null, FILTER_VALIDATE_INT);
        $cari = trim((string) ($f['cari'] ?? ''));

        return array_values(array_filter([
            $cari !== '' ? 'Cari: '.$cari : null,
            ($s = StatusSpt::tryFrom((string) ($f['status'] ?? ''))) ? 'Status: '.$s->label() : null,
            $irban ? 'Irban: '.Irban::whereKey($irban)->value('nama') : null,
            $bulan ? 'Bulan: '.now('Asia/Jakarta')->setDay(1)->setMonth($bulan)->translatedFormat('F') : null,
        ]));
    }

    public function html(array $f): string
    {
        return view('pdf.register-nomor', [
            'tahun' => (int) $f['tahun'], 'filter' => $this->ringkasanFilter($f), 'kolom' => self::KOLOM, 'baris' => $this->semua($f),
            'dibuat' => now('Asia/Jakarta')->translatedFormat('d F Y H:i').' WIB',
        ])->render();
    }

    public function pdf(array $f): string
    {
        return Pdf::loadHTML($this->html($f))->setPaper('a4', 'landscape')->output();
    }

    /** True once any SPT holds a number on this register: the starting number can no longer be set (PRD §7.7). */
    public function nomorAwalTerkunci(int $tahun, ?Irban $irban): bool
    {
        $counter = NomorCounter::where(app(Penomoran::class)->kunci($irban, $tahun))->first();

        return $counter && Spt::where('nomor_counter_id', $counter->id)->whereNotNull('nomor_urut')->exists();
    }

    private function bulan(array $f): int|false
    {
        return filter_var($f['bulan'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
    }
}
