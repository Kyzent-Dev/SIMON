<?php

namespace App\Http\Controllers\Publik;

use App\Http\Controllers\Controller;
use App\Models\LandingSlide;
use App\Services\Publik\RealisasiPublik;
use Illuminate\View\View;
use Throwable;

/** Public home (landing.md): hero slides (public CMS content) and this year's summary through RealisasiPublik only. */
class BerandaController extends Controller
{
    public function __invoke(RealisasiPublik $publik): View
    {
        $hariIni = now('Asia/Jakarta');
        $tahun = $hariIni->year;

        try {
            $ringkasan = $publik->ringkasan(['tahun' => $tahun]);
            $adaPkpt = $publik->adaDisahkan($tahun);
        } catch (Throwable $e) {
            report($e);
            $ringkasan = null; // landing.md States: "—" + "Data belum tersedia"
            $adaPkpt = false;
        }

        $slides = LandingSlide::where('aktif', true)->orderBy('urutan')->orderBy('id')->get()
            ->map(fn (LandingSlide $s) => [
                'judul' => $s->judul,
                'kutipan' => $s->kutipan,
                'pemilik' => implode(', ', array_filter([$s->pemilik_nama, $s->pemilik_jabatan])),
                'src960' => $s->gambarUrl(960),
                'src1920' => $s->gambarUrl(1920),
                'alt' => $s->dekoratif ? '' : (string) $s->alt,
            ])->all();

        return view('publik.beranda', [
            'tahun' => $tahun,
            'tanggal' => $hariIni->translatedFormat('d F Y'),
            'ringkasan' => $ringkasan,
            'adaPkpt' => $adaPkpt,
            'slides' => $slides,
        ]);
    }
}
