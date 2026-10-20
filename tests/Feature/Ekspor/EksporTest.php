<?php

namespace Tests\Feature\Ekspor;

use App\Actions\Spt\TerimaPengajuan;
use App\Enums\Role;
use App\Exports\ArrayExport;
use App\Jobs\BuatEkspor;
use App\Livewire\BagianUmum\Register;
use App\Models\Ekspor as EksporModel;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\EksporGagal;
use App\Notifications\EksporSiap;
use App\Services\Ekspor;
use App\Services\Ekspor\PembangunRegister;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Export pipeline (Fase 8, Task 2): immediate download up to 1000 rows, queued above, owner-only download, cleanup. */
class EksporTest extends TestCase
{
    use RefreshDatabase;

    private User $bu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Asia/Jakarta'));
        $this->bu = User::factory()->create(['role' => Role::BagianUmum]);
        $spt = Spt::factory()->menungguNomor()->create(['irban_id' => Irban::factory()->create(['kode_nomor' => 'INSPEKTORAT-II'])->id, 'uraian_untuk' => '=HYPERLINK("x")']);
        app(TerimaPengajuan::class)->handle($this->bu, $spt);
    }

    /** Pretend the register holds $n rows without creating them. */
    private function jumlahPalsu(int $n): void
    {
        $this->app->bind(PembangunRegister::class, fn () => new class($n) extends PembangunRegister
        {
            public function __construct(private int $n) {}

            public function jumlah(User $u, array $f): int
            {
                return $this->n;
            }
        });
    }

    private function buatRow(string $format): EksporModel
    {
        Queue::fake();
        $this->jumlahPalsu(Ekspor::BATAS_LANGSUNG + 1);
        app(Ekspor::class)->minta($this->bu, 'register', $format, ['tahun' => 2026]);
        $this->app->bind(PembangunRegister::class, fn () => new PembangunRegister);

        return EksporModel::latest('created_at')->latest('id')->firstOrFail();
    }

    public function test_up_to_limit_downloads_at_once_without_row_or_job(): void
    {
        Queue::fake();
        $this->jumlahPalsu(Ekspor::BATAS_LANGSUNG);

        $r = app(Ekspor::class)->minta($this->bu, 'register', 'xlsx', ['tahun' => 2026]);

        $this->assertNotNull($r);
        $this->assertSame(0, EksporModel::count());
        Queue::assertNothingPushed();
    }

    public function test_above_limit_stores_row_and_pushes_job_with_toast(): void
    {
        Queue::fake();
        $this->jumlahPalsu(Ekspor::BATAS_LANGSUNG + 1);

        Livewire::actingAs($this->bu)->test(Register::class)->call('ekspor', 'pdf')
            ->assertDispatched('toast', type: 'info', message: 'Ekspor sedang disiapkan. Anda akan menerima notifikasi saat file siap.');

        $e = EksporModel::firstOrFail();
        $this->assertSame(['menunggu', 'register', 'pdf', 'register-nomor-spt-2026.pdf', $this->bu->id], [$e->status, $e->jenis, $e->format, $e->nama_file, $e->user_id]);
        Queue::assertPushed(BuatEkspor::class, fn ($j) => $j->ekspor->is($e));
    }

    public function test_job_writes_xlsx_marks_done_and_notifies_only_requester(): void
    {
        Notification::fake();
        $lain = User::factory()->create(['role' => Role::BagianUmum]);
        $e = $this->buatRow('xlsx');

        app()->call([new BuatEkspor($e), 'handle']);

        $e->refresh();
        $this->assertSame('selesai', $e->status);
        $this->assertSame("ekspor/{$e->id}.xlsx", $e->path);
        Storage::disk('local')->assertExists($e->path);
        Notification::assertSentTo($this->bu, EksporSiap::class, function (EksporSiap $n) use ($e) {
            $this->assertSame("/ekspor/{$e->id}", $n->url());
            $this->assertSame('File ekspor siap', $n->judul());
            $this->assertSame($e->nama_file, $n->konteks());
            $this->assertSame('ekspor_siap', $n->jenis());
            $this->assertSame('Unduh', $n->toMail($this->bu)->actionText);

            return true;
        });
        Notification::assertNotSentTo($lain, EksporSiap::class);
        Notification::assertCount(1);
    }

    public function test_job_failure_sets_gagal_and_notifies(): void
    {
        Notification::fake();
        $e = $this->buatRow('xlsx');
        $this->app->bind(PembangunRegister::class, fn () => new class extends PembangunRegister
        {
            public function export(User $u, array $f): ArrayExport
            {
                throw new \RuntimeException('boom');
            }
        });

        app()->call([new BuatEkspor($e), 'handle']);

        $this->assertSame('gagal', $e->fresh()->status);
        Notification::assertSentTo($this->bu, EksporGagal::class, fn (EksporGagal $n) => $n->url() === '/bagian-umum/register' && $n->jenis() === 'ekspor_gagal' && $n->judul() === 'Ekspor gagal');
        Notification::assertNotSentTo($this->bu, EksporSiap::class);
    }

    public function test_job_rechecks_authorization_of_stored_user(): void
    {
        Notification::fake();
        $e = $this->buatRow('xlsx');
        $this->bu->forceFill(['role' => Role::Irban])->save();

        app()->call([new BuatEkspor($e), 'handle']);

        $this->assertSame('gagal', $e->fresh()->status);
        Storage::disk('local')->assertMissing("ekspor/{$e->id}.xlsx");
    }

    public function test_owner_downloads_valid_xlsx_and_pdf(): void
    {
        $x = $this->buatRow('xlsx');
        $p = $this->buatRow('pdf');
        app()->call([new BuatEkspor($x), 'handle']);
        app()->call([new BuatEkspor($p), 'handle']);

        $this->assertSame("/ekspor/{$x->id}", route('ekspor.unduh', $x, absolute: false));
        $r = $this->actingAs($this->bu)->get("/ekspor/{$x->id}")->assertOk()->assertDownload('register-nomor-spt-2026.xlsx');
        $tmp = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($tmp, $r->streamedContent());
        $sheet = IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);
        $this->assertSame('Nomor SPT', $sheet->getCell('B1')->getValue());
        $this->assertSame('=HYPERLINK("x")', $sheet->getCell('E2')->getValue());
        $this->assertSame('s', $sheet->getCell('E2')->getDataType());

        $pdf = $this->actingAs($this->bu)->get("/ekspor/{$p->id}")->assertOk()->assertDownload('register-nomor-spt-2026.pdf');
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
    }

    public function test_others_pending_and_guests_cannot_download(): void
    {
        $e = $this->buatRow('xlsx');
        $this->actingAs($this->bu)->get("/ekspor/{$e->id}")->assertNotFound(); // menunggu
        app()->call([new BuatEkspor($e), 'handle']);

        $lain = User::factory()->create(['role' => Role::BagianUmum]);
        $this->actingAs($lain)->get("/ekspor/{$e->id}")->assertNotFound();
        auth()->logout();
        $this->get("/ekspor/{$e->id}")->assertRedirect('/masuk');
    }

    public function test_cleanup_removes_only_files_and_rows_older_than_seven_days(): void
    {
        $lama = $this->buatRow('xlsx');
        $baru = $this->buatRow('xlsx');
        app()->call([new BuatEkspor($lama), 'handle']);
        app()->call([new BuatEkspor($baru), 'handle']);
        $lama->forceFill(['created_at' => now()->subDays(7)->subMinute()])->save();
        $baru->forceFill(['created_at' => now()->subDays(6)])->save();

        $this->artisan('ekspor:bersihkan')->assertSuccessful();

        $this->assertNull(EksporModel::find($lama->id));
        Storage::disk('local')->assertMissing("ekspor/{$lama->id}.xlsx");
        $this->assertNotNull(EksporModel::find($baru->id));
        Storage::disk('local')->assertExists("ekspor/{$baru->id}.xlsx");
    }

    public function test_schedule_registered(): void
    {
        $e = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'ekspor:bersihkan'));
        $this->assertNotNull($e);
        $this->assertSame('0 5 * * *', $e->expression);
        $this->assertSame('Asia/Jakarta', (string) $e->timezone);
        $this->assertTrue($e->withoutOverlapping);
    }
}
