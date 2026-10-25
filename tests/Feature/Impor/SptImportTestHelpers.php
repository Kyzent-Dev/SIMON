<?php

namespace Tests\Feature\Impor;

use App\Enums\StatusPkpt;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Klasifikasi;
use App\Models\Objek;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\User;
use App\Services\Import\SptImportParser;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** Shared seed and xlsx fixtures for the SPT import tests. */
trait SptImportTestHelpers
{
    public const S = '198001012005011001';

    public const K = '198001012005011002';

    public const A1 = '198001012005011003';

    public const A2 = '198001012005011004';

    public const X = '198001012005011005';

    protected User $admin;

    protected Irban $irban;

    protected JenisPenugasan $jenis;

    protected PkptItem $item;

    /** @var array<string, Pegawai> by NIP */
    protected array $pegawai = [];

    protected function seedImpor(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-25 10:00', 'Asia/Jakarta'));
        $this->admin = User::factory()->admin()->create();
        $this->irban = Irban::factory()->create(['nama' => 'Irban I', 'kode_nomor' => 'I']);
        $this->jenis = JenisPenugasan::factory()->create(['nama' => 'Reviu LKjIP']);
        foreach ([self::S, self::K, self::A1, self::A2, self::X] as $nip) {
            $this->pegawai[$nip] = Pegawai::factory()->create(['nip' => $nip]);
        }
        Objek::factory()->create(['nama' => 'Kecamatan Lawang Kidul']);
        Objek::factory()->create(['nama' => 'Kecamatan Tanjung Agung']);
        Objek::factory()->count(2)->create(['nama' => 'Desa Ganda']);
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2025]);
        $this->assertSame(StatusPkpt::Disahkan, $pkpt->status);
        $this->item = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $this->irban->id, 'jenis_penugasan_id' => $this->jenis->id,
            'tanggal_mulai' => '2025-03-01', 'tanggal_selesai' => '2025-03-31']);
    }

    protected function klasifikasiId(string $kode): int
    {
        return Klasifikasi::where('kode', $kode)->value('id');
    }

    /** A complete Reguler row, finished with LHP, linked to the PKPT item; keyed by E3 header. */
    protected function baris(array $o = []): array
    {
        return array_replace([
            'nomor_spt' => '700/751/I/SPT/2025', 'tanggal_spt' => '28/02/2025', 'irban' => 'Irban I', 'klasifikasi' => 'REGULER',
            'jenis_penugasan' => 'Reviu LKjIP', 'uraian_untuk' => 'Reviu LKjIP Tahun 2024', 'periode_diperiksa' => 'Tahun 2024',
            'objek' => 'Kecamatan Lawang Kidul', 'tanggal_mulai' => '03/03/2025', 'tanggal_selesai' => '14/03/2025',
            'nip_supervisor' => self::S, 'nip_ketua' => self::K, 'nip_anggota' => self::A1.';'.self::A2,
            'nomor_lhp' => '700/LHP/1/2025', 'tanggal_lhp' => '20/03/2025', 'selesai' => 'ya', 'kendala_kategori' => '', 'kendala_uraian' => '',
        ], $o);
    }

    /** Int/float values become numeric cells; everything else an explicit text cell. */
    protected function xlsx(array $rows, array $header = SptImportParser::HEADERS): string
    {
        $s = new Spreadsheet;
        $sheet = $s->getActiveSheet();
        foreach ($header as $c => $h) {
            $sheet->setCellValueExplicit([$c + 1, 1], $h, 's');
        }
        foreach ($rows as $r => $row) {
            foreach ($header as $c => $h) {
                $v = $row[$h] ?? '';
                is_int($v) || is_float($v) ? $sheet->setCellValueExplicit([$c + 1, $r + 2], $v, 'n') : $sheet->setCellValueExplicit([$c + 1, $r + 2], (string) $v, 's');
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'spt').'.xlsx';
        IOFactory::createWriter($s, 'Xlsx')->save($path);

        return $path;
    }

    protected function analisis(array $rows): array
    {
        $p = new SptImportParser;

        return $p->analyze($p->read($this->xlsx($rows)));
    }
}
