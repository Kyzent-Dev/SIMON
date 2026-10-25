<?php

namespace Tests\Feature\Impor;

use App\Actions\Spt\ImporSpt;
use App\Livewire\Admin\Impor as Page;
use App\Models\Aktivitas;
use App\Models\Spt;
use App\Models\User;
use App\Services\Import\SptImportParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class HalamanImporTest extends TestCase
{
    use RefreshDatabase, SptImportTestHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seedImpor();
    }

    private function unggah(array $rows)
    {
        return Livewire::actingAs($this->admin)->test(Page::class)
            ->set('file', UploadedFile::fake()->createWithContent('lama.xlsx', file_get_contents($this->xlsx($rows))));
    }

    /** 2 valid, 1 warning (no PKPT item for 2024), 1 error. */
    private function campuran(): array
    {
        return [
            $this->baris(['nomor_spt' => '700/1/I/SPT/2025']),
            $this->baris(['nomor_spt' => '700/2/I/SPT/2025']),
            $this->baris(['nomor_spt' => '700/3/I/SPT/2024', 'tanggal_spt' => '28/02/2024', 'tanggal_mulai' => '03/03/2024', 'tanggal_selesai' => '14/03/2024', 'nomor_lhp' => '', 'tanggal_lhp' => '', 'selesai' => 'tidak']),
            $this->baris(['nomor_spt' => '700/4/I/SPT/2025', 'irban' => 'Irban IX']),
        ];
    }

    public function test_akses(): void
    {
        $this->actingAs($this->admin)->get('/admin/impor')->assertOk()->assertSee('Impor Data Lama')->assertSee('Petunjuk kolom');
        foreach (['pimpinan', 'irban', 'bagianUmum'] as $state) {
            $u = User::factory()->{$state}()->create();
            $this->actingAs($u)->get('/admin/impor')->assertForbidden();
            Livewire::actingAs($u)->test(Page::class)->assertForbidden();
        }
        $c = Livewire::actingAs($this->admin)->test(Page::class);
        $this->actingAs(User::factory()->pimpinan()->create()); // actions re-check the gate for the current user
        $c->call('unduhTemplat')->assertForbidden();
    }

    public function test_unduh_templat(): void
    {
        $c = Livewire::actingAs($this->admin)->test(Page::class)->call('unduhTemplat')->assertFileDownloaded('templat-impor-spt.xlsx');
        $path = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        file_put_contents($path, $c->effects['download']['content'] ? base64_decode($c->effects['download']['content']) : '');
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame(SptImportParser::HEADERS, array_values(array_filter($sheet->rangeToArray('A1:R1')[0])));
        foreach (['A', 'K', 'L', 'M', 'N'] as $col) {
            $this->assertSame('@', $sheet->getColumnStyle($col)->getNumberFormat()->getFormatCode(), $col);
        }
        $this->assertNull($sheet->getColumnStyle('B'));
    }

    public function test_alur_unggah_pratinjau_impor(): void
    {
        $c = $this->unggah($this->campuran())->assertSet('step', 2)
            ->assertSee('2 valid')->assertSee('1 peringatan')->assertSee('1 galat')->assertSee('Impor 3 baris');
        $c->call('impor')->assertSet('step', 2);
        $this->assertSame(0, Spt::count());

        $c->set('lewati', true)->call('impor')->assertSet('step', 3)->assertSet('diimpor', 3)
            ->assertDispatched('toast', type: 'success', message: '3 SPT berhasil diimpor.')->assertSee('3 SPT berhasil diimpor.');
        $this->assertSame(3, Spt::count());
        $this->assertSame(3, Aktivitas::imporTerakhir()->properties['jumlah']);
        $this->assertNull($c->get('file'));
        $c->call('ulangi')->assertSet('step', 1)->assertSet('raw', []);
    }

    public function test_unduh_galat(): void
    {
        $c = $this->unggah($this->campuran())->call('unduhGalat')->assertFileDownloaded('galat-impor-spt.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'g').'.xlsx';
        file_put_contents($path, base64_decode($c->effects['download']['content']));
        $this->assertSame([['Baris', 'Kolom', 'Pesan'], ['5', 'irban', 'Irban "Irban IX" tidak ditemukan.']],
            array_map(fn ($r) => array_map('strval', $r), IOFactory::load($path)->getActiveSheet()->toArray()));
    }

    public function test_raw_dirusak_lebih_dari_maks(): void
    {
        $c = $this->unggah($this->campuran())->assertSet('step', 2);
        $c->set('raw', array_fill(0, SptImportParser::MAX_ROWS + 1, ['baris' => 2]))
            ->assertSet('step', 1)->assertSee(SptImportParser::capMessage());
    }

    public function test_toast_balapan(): void
    {
        $c = $this->unggah([$this->baris(['nomor_spt' => '700/9/I/SPT/2025'])])->assertSet('step', 2);
        // The number is taken between the server-side recompute and the write: ImporSpt turns the unique violation into this exception.
        $this->mock(ImporSpt::class)->shouldReceive('handle')->andThrow(ValidationException::withMessages(['impor' => 'Nomor SPT 700/9/I/SPT/2025 sudah dipakai. Muat ulang pratinjau.']));
        $c->call('impor')->assertSet('step', 2)->assertDispatched('toast', type: 'error', message: 'Nomor SPT 700/9/I/SPT/2025 sudah dipakai. Muat ulang pratinjau.');
    }

    public function test_kartu_impor_terakhir(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertSee('Impor terakhir')->assertSee('Belum ada');
        Aktivitas::catatImpor($this->admin, 'spt', 'a.xlsx', 12);
        $this->actingAs($this->admin)->get('/admin')->assertSee('12 baris')->assertSee('SPT data lama');
    }
}
