<?php

namespace Database\Seeders;

use App\Models\LandingSlide;
use App\Services\Settings;
use App\Support\GambarSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/** Demo home content (non-production only): 3 slides with GD-drawn landscapes in brand colors and demo kontak values. */
class LandingSeeder extends Seeder
{
    private const SLIDES = [
        ['judul' => 'Mengawal akuntabilitas pemerintahan Kabupaten Muara Enim', 'kutipan' => 'Pengawasan yang terencana membantu setiap perangkat daerah bekerja lebih tertib dan terbuka.',
            'pemilik_nama' => 'Inspektur Daerah', 'pemilik_jabatan' => 'Inspektorat Daerah Kabupaten Muara Enim', 'alt' => 'Ilustrasi perbukitan hijau di bawah langit cerah', 'warna' => [[20, 92, 74], [143, 224, 192], [242, 193, 78]]],
        ['judul' => 'Jadwal pengawasan terbuka untuk publik', 'kutipan' => 'Lihat rencana pemeriksaan sepanjang tahun dan kemajuannya.',
            'alt' => 'Ilustrasi sungai berkelok di antara sawah', 'warna' => [[8, 43, 35], [94, 203, 165], [188, 237, 217]]],
        ['judul' => 'Realisasi pengawasan diperbarui berkala', 'kutipan' => null, 'dekoratif' => true, 'warna' => [[18, 76, 62], [52, 176, 138], [242, 193, 78]]],
    ];

    public function run(): void
    {
        if (LandingSlide::exists()) {
            return;
        }
        // Empty table (e.g. migrate:fresh): drop slide files left by an earlier seed.
        Storage::disk('public')->deleteDirectory('slide');
        foreach (self::SLIDES as $i => $s) {
            LandingSlide::create([
                'judul' => $s['judul'], 'kutipan' => $s['kutipan'], 'pemilik_nama' => $s['pemilik_nama'] ?? null, 'pemilik_jabatan' => $s['pemilik_jabatan'] ?? null,
                'alt' => $s['alt'] ?? null, 'dekoratif' => $s['dekoratif'] ?? false, 'aktif' => true, 'urutan' => $i + 1,
                'gambar' => GambarSlide::tulis($this->lanskap($s['warna'], $i)),
            ]);
        }

        $settings = app(Settings::class);
        $settings->saveGroup('kontak', ['kontak' => [
            'telepon' => '(0734) 421001',
            'email' => 'inspektorat@muaraenimkab.go.id',
            'instagram' => 'inspektorat.muaraenim',
            'alamat' => "Jl. Jenderal Sudirman No. 1\nMuara Enim, Sumatera Selatan 31311",
            'peta_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d127478!2d103.73!3d-3.65!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1',
        ]]);
    }

    /** A 1920x1080 landscape: sky gradient, sun, three layered hills. */
    private function lanskap(array $warna, int $variasi): \GdImage
    {
        [$gelap, $terang, $matahari] = $warna;
        $w = 1920;
        $h = 1080;
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            $c = array_map(fn ($a, $b) => (int) round($a + ($b - $a) * $t), $terang, $gelap);
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, ...$c));
        }
        imagefilledellipse($img, 1400 - $variasi * 250, 300, 220, 220, imagecolorallocate($img, ...$matahari));
        foreach ([[0.55, 0.6], [0.7, 0.8], [0.85, 1.0]] as $k => [$dasar, $gelapnya]) {
            $c = array_map(fn ($a, $b) => (int) round($b + ($a - $b) * $gelapnya), $gelap, $terang);
            $poin = [0, $h];
            for ($x = 0; $x <= $w; $x += 40) {
                $poin[] = $x;
                $poin[] = (int) ($h * $dasar + sin(($x / $w) * M_PI * (2 + $k) + $variasi) * 60);
            }
            array_push($poin, $w, $h);
            imagefilledpolygon($img, $poin, imagecolorallocate($img, ...$c));
        }

        return $img;
    }
}
