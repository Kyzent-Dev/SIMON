<?php

namespace Tests\Feature\MasterData;

use App\Actions\MasterData\ImportPegawai;
use App\Actions\MasterData\SaveIrban;
use App\Actions\MasterData\SaveIrbanTahunan;
use App\Actions\MasterData\SavePegawai;
use App\Actions\MasterData\SaveTim;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Tim;
use App\Models\TimAnggota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinalReviewFixTest extends TestCase
{
    use RefreshDatabase;

    private function errors(callable $fn): array
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return $e->errors();
        }
        $this->fail('Expected ValidationException');
    }

    /** Inserts $row through the query builder right before the model's own INSERT (simulates a concurrent save). */
    private function raceOnCreate(string $model, string $table, array $row): void
    {
        $model::creating(function () use ($table, $row) {
            DB::table($table)->insert($row);
        });
    }

    private function pegawaiData(string $nip, array $over = []): array
    {
        return [...['nama' => 'Budi, S.E.', 'nip' => $nip, 'pangkat' => null, 'golongan' => null, 'jabatan' => null, 'aktif' => true], ...$over];
    }

    public function test_save_pegawai_race_on_nip_is_a_validation_error(): void
    {
        $nip = '123456789012345678';
        $this->raceOnCreate(Pegawai::class, 'pegawai', ['nama_bergelar' => 'X', 'nip' => $nip, 'aktif' => true]);

        $e = $this->errors(fn () => app(SavePegawai::class)->handle(null, $this->pegawaiData($nip)));
        $this->assertSame(['NIP sudah terdaftar.'], $e['nip']);
    }

    public function test_import_pegawai_race_on_nip_is_a_validation_error(): void
    {
        $nip = '123456789012345678';
        $this->raceOnCreate(Pegawai::class, 'pegawai', ['nama_bergelar' => 'X', 'nip' => $nip, 'aktif' => true]);

        $e = $this->errors(fn () => app(ImportPegawai::class)->handle([['status' => 'valid', 'data' => $this->pegawaiData($nip)]]));
        $this->assertStringContainsString('Impor dibatalkan', $e['file'][0]);
    }

    public function test_save_pegawai_trims_string_fields(): void
    {
        $p = app(SavePegawai::class)->handle(null, $this->pegawaiData('123456789012345678', ['nama' => '  Budi  ', 'pangkat' => ' Penata ', 'golongan' => '   ', 'jabatan' => ' Auditor ']));

        $p->refresh();
        $this->assertSame('Budi', $p->nama_bergelar);
        $this->assertSame('Penata', $p->pangkat);
        $this->assertNull($p->golongan);
        $this->assertSame('Auditor', $p->jabatan);
    }

    public function test_save_irban_race_on_nama_and_kode(): void
    {
        $this->raceOnCreate(Irban::class, 'irban', ['nama' => 'Irban Satu', 'kode_nomor' => 'K-1', 'created_at' => now(), 'updated_at' => now()]);
        $e = $this->errors(fn () => app(SaveIrban::class)->handle(null, ['nama' => 'Irban Satu', 'kodeNomor' => 'K-2', 'emailNotifikasi' => null, 'tembusan' => []]));
        $this->assertSame(['Nama Irban sudah dipakai.'], $e['nama']);
    }

    public function test_save_irban_race_on_kode(): void
    {
        $this->raceOnCreate(Irban::class, 'irban', ['nama' => 'Irban Lain', 'kode_nomor' => 'K-1', 'created_at' => now(), 'updated_at' => now()]);
        $e = $this->errors(fn () => app(SaveIrban::class)->handle(null, ['nama' => 'Irban Satu', 'kodeNomor' => 'K-1', 'emailNotifikasi' => null, 'tembusan' => []]));
        $this->assertSame(['Kode nomor sudah dipakai.'], $e['kodeNomor']);
    }

    public function test_save_tim_race_on_nama(): void
    {
        $irban = Irban::factory()->create();
        $tahun = (int) now('Asia/Jakarta')->year;
        $this->raceOnCreate(Tim::class, 'tim', ['irban_id' => $irban->id, 'tahun' => $tahun, 'nama' => 'Tim A', 'created_at' => now(), 'updated_at' => now()]);
        $members = [
            ['pegawai_id' => Pegawai::factory()->create()->id, 'peran_default' => 'ketua'],
            ['pegawai_id' => Pegawai::factory()->create()->id, 'peran_default' => 'supervisor_dalnis'],
        ];

        $e = $this->errors(fn () => app(SaveTim::class)->handle($irban, $tahun, null, 'Tim A', $members));
        $this->assertSame(['Nama tim sudah dipakai pada Irban dan tahun ini.'], $e['nama']);
    }

    public function test_save_tim_race_on_anggota(): void
    {
        $irban = Irban::factory()->create();
        $tahun = (int) now('Asia/Jakarta')->year;
        $a = Pegawai::factory()->create();
        $b = Pegawai::factory()->create();
        TimAnggota::creating(function (TimAnggota $m) use ($a) {
            if ($m->pegawai_id === $a->id) {
                DB::table('tim_anggota')->insert(['tim_id' => $m->tim_id, 'pegawai_id' => $a->id, 'peran_default' => 'anggota', 'urutan' => 9]);
            }
        });

        $e = $this->errors(fn () => app(SaveTim::class)->handle($irban, $tahun, null, 'Tim B', [
            ['pegawai_id' => $a->id, 'peran_default' => 'ketua'], ['pegawai_id' => $b->id, 'peran_default' => 'supervisor_dalnis'],
        ]));
        $this->assertArrayHasKey('members', $e);
        $this->assertSame(0, Tim::count());
    }

    public function test_save_irban_tahunan_rejects_out_of_range_year(): void
    {
        $irban = Irban::factory()->create();
        $y = (int) now('Asia/Jakarta')->year;
        foreach ([$y - 3, $y + 2] as $bad) {
            $e = $this->errors(fn () => app(SaveIrbanTahunan::class)->handle($irban, $bad, null, []));
            $this->assertSame(['Tahun tidak valid.'], $e['tahun']);
        }
    }
}
