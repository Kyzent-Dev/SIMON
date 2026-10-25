<?php

namespace Tests\Feature\Impor;

use App\Actions\Spt\ImporSpt;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\Tim;
use App\Models\User;
use App\Services\Import\SptImportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** PRD §12 Fase 10: the example file (demo seed data) imports 5 rows and rejects 2 with clear messages. */
class FileContohTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<array<string, string>> keyed by SptImportParser::HEADERS */
    private function barisContoh(): array
    {
        $tim = Tim::where('nama', 'Tim 1')->firstOrFail()->anggota()->orderBy('urutan')->get();
        [$sup, $ketua, $a1, $a2] = $tim->map(fn ($a) => Pegawai::findOrFail($a->pegawai_id)->nip)->all();
        $dasar = [
            'irban' => 'Irban I', 'klasifikasi' => 'REGULER', 'jenis_penugasan' => 'Reviu LKjIP', 'periode_diperiksa' => 'Tahun 2023',
            'nip_supervisor' => $sup, 'nip_ketua' => $ketua, 'nip_anggota' => "$a1;$a2",
            'kendala_kategori' => '', 'kendala_uraian' => '',
        ];
        $baris = fn (array $o) => array_replace($dasar, $o);

        return [
            $baris(['nomor_spt' => '700/801/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '05/02/2024', 'uraian_untuk' => 'Reviu LKjIP Tahun 2023',
                'objek' => 'Muara Enim', 'tanggal_mulai' => '12/02/2024', 'tanggal_selesai' => '23/02/2024',
                'nomor_lhp' => '700/LHP/1/2024', 'tanggal_lhp' => '28/02/2024', 'selesai' => 'ya']),
            $baris(['nomor_spt' => '700/802/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '04/03/2024', 'uraian_untuk' => 'Pengawasan Dana BOS Tahun 2023',
                'jenis_penugasan' => 'Pengawasan Dana BOS', 'objek' => 'Ujan Mas;Gunung Megang', 'tanggal_mulai' => '11/03/2024', 'tanggal_selesai' => '22/03/2024',
                'nomor_lhp' => '700/LHP/2/2024', 'tanggal_lhp' => '29/03/2024', 'selesai' => 'ya']),
            $baris(['nomor_spt' => '700/803/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '2024-05-06', 'uraian_untuk' => 'Evaluasi RB dan Reviu DAK',
                'jenis_penugasan' => 'Evaluasi RB dan Reviu DAK', 'objek' => 'Dinas Pendidikan', 'tanggal_mulai' => '2024-05-13', 'tanggal_selesai' => '2024-05-24',
                'nomor_lhp' => '', 'tanggal_lhp' => '', 'selesai' => 'tidak', 'kendala_kategori' => 'Lainnya', 'kendala_uraian' => 'Data belum lengkap']),
            $baris(['nomor_spt' => '700/804/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '03/06/2024', 'klasifikasi' => 'KHUSUS', 'jenis_penugasan' => '',
                'uraian_untuk' => 'Audit khusus pengadaan', 'periode_diperiksa' => '', 'objek' => 'Dinas PUPR', 'tanggal_mulai' => '10/06/2024',
                'tanggal_selesai' => '21/06/2024', 'nomor_lhp' => '', 'tanggal_lhp' => '', 'selesai' => 'tidak']),
            $baris(['nomor_spt' => '700/805/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '01/07/2024', 'uraian_untuk' => 'Reviu LKjIP Semester I',
                'objek' => 'BAPPEDA', 'tanggal_mulai' => '08/07/2024', 'tanggal_selesai' => '19/07/2024', 'nip_supervisor' => '', 'nip_anggota' => '',
                'nomor_lhp' => '', 'tanggal_lhp' => '', 'selesai' => '']),
            // Bad rows.
            $baris(['nomor_spt' => '700/806/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '05/08/2024', 'irban' => 'Irban IX', 'uraian_untuk' => 'Irban tidak ada',
                'objek' => 'BKPSDM', 'tanggal_mulai' => '12/08/2024', 'tanggal_selesai' => '23/08/2024', 'nomor_lhp' => '', 'tanggal_lhp' => '', 'selesai' => 'tidak']),
            $baris(['nomor_spt' => '700/807/INSPEKTORAT-I/SPT/2024', 'tanggal_spt' => '02/09/2024', 'uraian_untuk' => 'LHP tanpa tanggal',
                'objek' => 'BPKAD', 'tanggal_mulai' => '09/09/2024', 'tanggal_selesai' => '20/09/2024', 'nomor_lhp' => '700/LHP/7/2024', 'tanggal_lhp' => '', 'selesai' => 'ya']),
        ];
    }

    /** Every cell is an explicit string, like the downloaded template's text columns. */
    private function berkas(array $rows): string
    {
        $s = new Spreadsheet;
        $sheet = $s->getActiveSheet();
        foreach (SptImportParser::HEADERS as $c => $h) {
            $sheet->setCellValueExplicit([$c + 1, 1], $h, 's');
            foreach ($rows as $r => $row) {
                $sheet->setCellValueExplicit([$c + 1, $r + 2], (string) $row[$h], 's');
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'contoh').'.xlsx';
        IOFactory::createWriter($s, 'Xlsx')->save($path);

        return $path;
    }

    public function test_file_contoh_mengimpor_lima_baris_dan_menolak_dua(): void
    {
        $this->seed();
        $parser = new SptImportParser;
        $rows = $parser->analyze($parser->read($this->berkas($this->barisContoh())));

        $this->assertCount(7, $rows);
        foreach (array_slice($rows, 0, 5) as $r) {
            $this->assertNotSame('error', $r['status'], json_encode($r['errors']));
        }
        $this->assertSame(['Irban "Irban IX" tidak ditemukan.'], array_column($rows[5]['errors'], 'pesan'));
        $this->assertSame(['Nomor dan tanggal LHP diisi bersama.'], array_column($rows[6]['errors'], 'pesan'));

        $admin = User::where('username', 'admin')->firstOrFail();
        $sebelum = Spt::count();
        $this->assertSame(5, app(ImporSpt::class)->handle($admin, $rows, 'contoh.xlsx'));
        $this->assertSame($sebelum + 5, Spt::count());
    }
}
