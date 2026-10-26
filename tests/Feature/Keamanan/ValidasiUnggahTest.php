<?php

namespace Tests\Feature\Keamanan;

use App\Actions\Spt\UnggahDokumenSpt;
use App\Livewire\Admin\Impor;
use App\Livewire\Admin\PegawaiImpor;
use App\Livewire\Admin\Pengaturan;
use App\Livewire\Admin\PkptImpor;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\Pkpt;
use App\Models\Spt;
use App\Models\User;
use App\Services\Settings;
use App\Support\BerkasSpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Fase 11 Task 2 (E2): a file that lies about its type is refused, never stored; upload.maks_mb is capped at 12. */
class ValidasiUnggahTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public static function berkasPalsu(): array
    {
        return [
            'php bernama pdf' => ['<?php echo 1;'],
            'html bernama pdf' => ['<html><script>alert(1)</script></html>'],
            'teks biasa' => ['bukan pdf sungguhan'],
        ];
    }

    #[DataProvider('berkasPalsu')]
    public function test_pdf_palsu_ditolak(string $isi): void
    {
        $f = UploadedFile::fake()->createWithContent('x.pdf', $isi);
        $this->assertFalse(BerkasSpt::pdf($f, 10 * 1024 * 1024));
    }

    public function test_pdf_asli_diterima_dan_batas_ukuran(): void
    {
        $f = UploadedFile::fake()->createWithContent('x.pdf', self::PDF);
        $this->assertTrue(BerkasSpt::pdf($f, 10 * 1024 * 1024));
        $this->assertFalse(BerkasSpt::pdf($f, strlen(self::PDF) - 1));
    }

    public function test_unggah_dokumen_spt_menolak_php_bernama_pdf_tanpa_menyimpan(): void
    {
        $irban = Irban::factory()->create();
        $penginput = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $irban->id, 'tahun' => now('Asia/Jakarta')->year, 'ppj_pegawai_id' => $penginput->id]);
        $user = User::factory()->irban()->create();
        $user->forceFill(['irban_id' => $irban->id])->save();
        $spt = Spt::factory()->diterima()->create(['irban_id' => $irban->id]);

        try {
            app(UnggahDokumenSpt::class)->handle($user, $spt, ['diinput_oleh' => $penginput->id], UploadedFile::fake()->createWithContent('x.pdf', '<?php echo 1;'));
            $this->fail('Harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('berkas', $e->errors());
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    #[DataProvider('halamanImpor')]
    public function test_csv_bernama_xlsx_ditolak(string $halaman): void
    {
        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test($halaman, $halaman === PkptImpor::class ? ['pkpt' => Pkpt::factory()->create()] : [])
            ->set('file', UploadedFile::fake()->createWithContent('x.xlsx', "a,b\n1,2\n"))
            ->assertHasErrors('file');
    }

    public static function halamanImpor(): array
    {
        return [[Impor::class], [PegawaiImpor::class], [PkptImpor::class]];
    }

    public function test_pengaturan_menolak_lebih_dari_12(): void
    {
        $c = Livewire::actingAs(User::factory()->admin()->create())->test(Pengaturan::class);
        $c->set('v.upload.maks_mb', 13)->call('save', 'unggahan')->assertHasErrors('v.upload.maks_mb');
        $c->set('v.upload.maks_mb', 12)->call('save', 'unggahan')->assertHasNoErrors();
        $this->assertSame(12, app(Settings::class)->get('upload.maks_mb'));
    }

    public function test_migrasi_menjepit_nilai_tersimpan_ke_12(): void
    {
        DB::table('settings')->insert(['key' => 'upload.maks_mb', 'value' => '50', 'created_at' => now(), 'updated_at' => now()]);
        $migrasi = require database_path('migrations/2026_12_01_000001_batasi_upload_maks_mb.php');

        $migrasi->up();
        $migrasi->up();

        $this->assertSame('12', DB::table('settings')->where('key', 'upload.maks_mb')->value('value'));
    }

    public function test_migrasi_tidak_mengubah_nilai_di_bawah_batas(): void
    {
        DB::table('settings')->insert(['key' => 'upload.maks_mb', 'value' => '8', 'created_at' => now(), 'updated_at' => now()]);
        (require database_path('migrations/2026_12_01_000001_batasi_upload_maks_mb.php'))->up();

        $this->assertSame('8', DB::table('settings')->where('key', 'upload.maks_mb')->value('value'));
    }
}
