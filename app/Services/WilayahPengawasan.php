<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/** Wilayah pengawasan per irban untuk satu tahun (irban_wilayah), dipakai halaman publik dan halaman Irban. */
class WilayahPengawasan
{
    /** irban_wilayah objek jenis → daftar. */
    private const DAFTAR = ['kecamatan' => 'kecamatan', 'dinas_badan' => 'dinas_badan', 'bumd' => 'bumd', 'rsud' => 'rsud'];

    /** @return list<array{irban_id: int, irban: string, kecamatan: list<string>, dinas_badan: list<string>, bumd: list<string>, rsud: list<string>}> ordered by irban name, names sorted */
    public function untuk(int $tahun, ?int $irbanId = null): array
    {
        $objek = DB::table('irban_wilayah')->join('objek', 'objek.id', '=', 'irban_wilayah.objek_id')
            ->where('irban_wilayah.tahun', $tahun)->whereIn('objek.jenis', array_keys(self::DAFTAR))
            ->when($irbanId, fn ($q) => $q->where('irban_wilayah.irban_id', $irbanId))
            ->orderBy('objek.nama')->get(['irban_wilayah.irban_id', 'objek.nama', 'objek.jenis'])->groupBy('irban_id');

        return DB::table('irban')->when($irbanId, fn ($q) => $q->where('id', $irbanId))->orderBy('nama')->get(['id', 'nama'])->map(function ($i) use ($objek) {
            $daftar = ['kecamatan' => [], 'dinas_badan' => [], 'bumd' => [], 'rsud' => []];
            foreach ($objek[$i->id] ?? [] as $o) {
                $daftar[self::DAFTAR[$o->jenis]][] = $o->nama;
            }

            return ['irban_id' => $i->id, 'irban' => $i->nama] + $daftar;
        })->all();
    }
}
