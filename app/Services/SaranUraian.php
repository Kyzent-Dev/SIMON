<?php

namespace App\Services;

class SaranUraian
{
    /** "Melaksanakan {jenis} pada A, B dan C untuk {periode}." @param list<string> $objek */
    public function saran(string $jenis, array $objek, ?string $periode): string
    {
        $objek = array_values(array_filter($objek, fn ($o) => trim($o) !== ''));
        $akhir = array_pop($objek);
        $daftar = $objek ? implode(', ', $objek).' dan '.$akhir : (string) $akhir;
        $periode = trim((string) $periode);

        return 'Melaksanakan '.$jenis.($daftar !== '' ? ' pada '.$daftar : '').($periode !== '' ? ' untuk '.$periode : '').'.';
    }
}
