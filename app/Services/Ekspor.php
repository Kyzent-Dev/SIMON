<?php

namespace App\Services;

use App\Jobs\BuatEkspor;
use App\Models\Ekspor as EksporModel;
use App\Models\User;
use App\Services\Ekspor\Pembangun;
use App\Services\Ekspor\PembangunMonitoring;
use App\Services\Ekspor\PembangunRegister;
use App\Services\Ekspor\PembangunRekap;
use App\Services\Ekspor\PembangunRekapTahunan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/** One export pipeline for every table: up to BATAS_LANGSUNG rows (BATAS_LANGSUNG_PDF for pdf) download at once, more are built by a queued job (_shared.md section 6). */
class Ekspor
{
    public const BATAS_LANGSUNG = 1000;

    /** dompdf needs about 0,26 MB per row, so a direct PDF must stay well inside a 128M web request. */
    public const BATAS_LANGSUNG_PDF = 200;

    private const PEMBANGUN = [
        'register' => PembangunRegister::class, 'monitoring' => PembangunMonitoring::class, 'rekap' => PembangunRekap::class,
        'rekap-tahunan' => PembangunRekapTahunan::class,
    ];

    /** Jenis without a PDF form (ruling 1.1.4 Task 4). */
    private const HANYA_XLSX = ['rekap-tahunan'];

    public function pembangun(string $jenis): Pembangun
    {
        return app(self::PEMBANGUN[$jenis] ?? abort(404));
    }

    /** @return Response|null the download, or null when the export was queued. */
    public function minta(User $user, string $jenis, string $format, array $filter): ?Response
    {
        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 422);
        abort_if($format === 'pdf' && in_array($jenis, self::HANYA_XLSX, true), 422);
        $p = $this->pembangun($jenis);
        $p->otorisasi($user);
        // Counted only after authorization, so a refused request neither spends the quota nor hides the 403 behind "too many requests".
        $kunci = 'ekspor:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            throw ValidationException::withMessages(['ekspor' => 'Terlalu banyak permintaan ekspor. Coba lagi dalam '.RateLimiter::availableIn($kunci).' detik.']);
        }
        RateLimiter::hit($kunci, 60);
        $filter = $p->filter($user, $filter);
        $nama = $p->namaFile($user, $filter).".{$format}";

        if ($p->jumlah($user, $filter) <= ($format === 'pdf' ? self::BATAS_LANGSUNG_PDF : self::BATAS_LANGSUNG)) {
            return $format === 'xlsx'
                ? Excel::download($p->export($user, $filter), $nama)
                : response()->streamDownload(function () use ($p, $user, $filter) {
                    echo $this->pdf($p, $user, $filter);
                }, $nama, ['Content-Type' => 'application/pdf']);
        }

        BuatEkspor::dispatch(EksporModel::create([
            'user_id' => $user->id, 'jenis' => $jenis, 'format' => $format, 'filter' => $filter, 'nama_file' => $nama, 'status' => 'menunggu',
        ]));

        return null;
    }

    /** Builds the file of a queued export on the private disk; the builder re-checks authorization and re-scopes the filter. */
    public function tulis(EksporModel $ekspor): void
    {
        $user = $ekspor->user;
        $p = $this->pembangun($ekspor->jenis);
        $p->otorisasi($user);
        $filter = $p->filter($user, $ekspor->filter);
        $path = "ekspor/{$ekspor->id}.{$ekspor->format}";
        // Stored before writing: a worker killed mid-write never runs failed(), and ekspor:bersihkan finds the partial file only through `path`.
        $ekspor->update(['path' => $path]);

        if ($ekspor->format === 'xlsx') {
            Excel::store($p->export($user, $filter), $path, 'local');
        } else {
            Storage::disk('local')->put($path, $this->pdf($p, $user, $filter));
        }
        $ekspor->update(['path' => $path, 'status' => 'selesai']);
    }

    private function pdf(Pembangun $p, User $user, array $filter): string
    {
        return Pdf::loadHTML($p->html($user, $filter))->setPaper('a4', 'landscape')->output();
    }
}
