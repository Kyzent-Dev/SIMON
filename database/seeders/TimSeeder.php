<?php

namespace Database\Seeders;

use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\Tim;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Dev only, like UserSeeder. Irban I for the current year: Komposisi (PPJ + 3 anggota, needed by "Diinput oleh") and Tim 1 (1 Supervisor/Dalnis, 1 Ketua, 2 Anggota).
class TimSeeder extends Seeder
{
    public function run(): void
    {
        $irban = Irban::where('nama', 'Irban I')->first();
        $tahun = (int) now('Asia/Jakarta')->year;
        if (app()->isProduction() || ! $irban) {
            return;
        }

        $pegawai = Pegawai::where('aktif', true)->orderBy('id')->limit(4)->get();
        if ($pegawai->count() < 4) {
            $pegawai = $pegawai->concat(Pegawai::factory()->count(4 - $pegawai->count())->create());
        }

        // Demo: the first two pegawai get an address, the rest stay empty (to try both cases).
        foreach ($pegawai->take(2) as $p) {
            if ($p->email === null) {
                $p->update(['email' => Str::slug($p->nama_bergelar).'@example.test']);
            }
        }

        $komposisi = IrbanTahunan::firstOrCreate(['irban_id' => $irban->id, 'tahun' => $tahun], ['ppj_pegawai_id' => $pegawai[0]->id]);
        if ($komposisi->wasRecentlyCreated) {
            $komposisi->anggota()->attach($pegawai->slice(1)->pluck('id'));
        }

        if (Tim::where(['irban_id' => $irban->id, 'tahun' => $tahun, 'nama' => 'Tim 1'])->exists()) {
            return;
        }

        $tim = Tim::create(['irban_id' => $irban->id, 'tahun' => $tahun, 'nama' => 'Tim 1']);
        foreach (['supervisor_dalnis', 'ketua', 'anggota', 'anggota'] as $i => $peran) {
            $tim->anggota()->create(['pegawai_id' => $pegawai[$i]->id, 'peran_default' => $peran, 'urutan' => $i + 1]);
        }
    }
}
