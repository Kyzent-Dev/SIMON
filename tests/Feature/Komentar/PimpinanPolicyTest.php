<?php

namespace Tests\Feature\Komentar;

use App\Actions\Spt\BatalkanNomor;
use App\Actions\Spt\CatatNomorManual;
use App\Actions\Spt\MintaRevisi;
use App\Actions\Spt\SimpanKendala;
use App\Actions\Spt\SimpanSpt;
use App\Actions\Spt\TandaiSelesai;
use App\Actions\Spt\TerimaPengajuan;
use App\Actions\Spt\TolakPengajuan;
use App\Actions\Spt\UbahTanggalRealisasi;
use App\Actions\Spt\UnggahDokumenSpt;
use App\Livewire\Spt\Detail;
use App\Models\Irban;
use App\Models\Klasifikasi;
use App\Models\Spt;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** PRD Fase 7 "Selesai jika": pimpinan can comment but can never change SPT data. */
class PimpinanPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pimpinan = User::factory()->pimpinan()->create();
    }

    public static function statusSpt(): array
    {
        return ['menunggu nomor' => ['menungguNomor'], 'perlu revisi' => ['perluRevisi'], 'diterima' => ['diterima'], 'selesai' => ['selesai']];
    }

    #[DataProvider('statusSpt')]
    public function test_pimpinan_comments_but_cannot_change_spt(string $state): void
    {
        $spt = Spt::factory()->{$state}()->create();
        $g = Gate::forUser($this->pimpinan);

        foreach (['komentari', 'balasKomentar', 'tutupPermintaan'] as $a) {
            $this->assertTrue($g->allows($a, $spt), $a);
        }
        foreach (['update', 'laksanakan', 'decide', 'kelolaNomor'] as $a) {
            $this->assertFalse($g->allows($a, $spt), $a);
        }
    }

    public function test_pimpinan_cannot_see_or_comment_on_draf(): void
    {
        $spt = Spt::factory()->create();
        $g = Gate::forUser($this->pimpinan);

        foreach (['view', 'komentari', 'balasKomentar', 'tutupPermintaan', 'update', 'laksanakan', 'decide'] as $a) {
            $this->assertFalse($g->allows($a, $spt), $a);
        }
    }

    public function test_ability_matrix_of_other_roles(): void
    {
        $spt = Spt::factory()->diterima()->create();
        $irban = User::factory()->irban()->create();
        $irban->forceFill(['irban_id' => $spt->irban_id])->save();
        $lain = User::factory()->irban()->create();
        $lain->forceFill(['irban_id' => Irban::factory()->create()->id])->save();
        $matriks = [
            // user, komentari, balasKomentar, tutupPermintaan, view
            [$irban, false, true, false, true],
            [$lain, false, false, false, false],
            [User::factory()->bagianUmum()->create(), false, true, false, true],
            [User::factory()->admin()->create(), false, false, false, true],
        ];
        foreach ($matriks as [$u, $komentari, $balas, $tutup, $view]) {
            $g = Gate::forUser($u);
            $baris = $u->role->value.' '.$u->irban_id;
            $this->assertSame($komentari, $g->allows('komentari', $spt), "komentari $baris");
            $this->assertSame($balas, $g->allows('balasKomentar', $spt), "balas $baris");
            $this->assertSame($tutup, $g->allows('tutupPermintaan', $spt), "tutup $baris");
            $this->assertSame($view, $g->allows('view', $spt), "view $baris");
        }

        $draf = Spt::factory()->create(['irban_id' => $spt->irban_id]);
        foreach ([$this->pimpinan, User::factory()->bagianUmum()->create(), User::factory()->admin()->create()] as $u) {
            foreach (['komentari', 'balasKomentar', 'tutupPermintaan'] as $a) {
                $this->assertFalse(Gate::forUser($u)->allows($a, $draf), $a);
            }
        }
        // The owning Irban sees their own draf, yet never starts or closes a thread.
        $this->assertFalse(Gate::forUser($irban)->allows('komentari', $draf));
        $this->assertFalse(Gate::forUser($irban)->allows('tutupPermintaan', $draf));
    }

    /** Everything a write could touch, to prove nothing changed. */
    private function jejak(): array
    {
        return [
            'spt' => DB::table('spt')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'riwayat' => DB::table('spt_riwayat')->count(), 'dokumen' => DB::table('spt_dokumen')->count(),
            'kendala' => DB::table('spt_kendala')->count(), 'personel' => DB::table('spt_personel')->count(),
        ];
    }

    private function tolak(callable $fn): void
    {
        $sebelum = $this->jejak();
        try {
            $fn();
            $this->fail('Pimpinan write must be refused');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }
        $this->assertSame($sebelum, $this->jejak());
    }

    public function test_every_spt_write_action_refuses_pimpinan_and_changes_nothing(): void
    {
        $p = $this->pimpinan;
        $diterima = Spt::factory()->diterima()->create();
        $selesai = Spt::factory()->selesai()->create();
        $menunggu = Spt::factory()->menungguNomor()->create();
        $revisi = Spt::factory()->perluRevisi()->create();
        $draf = Spt::factory()->create();
        $kendala = ['kategori' => 'Lainnya', 'uraian' => 'Jadwal bergeser karena objek belum siap.', 'diinput_oleh' => 1];

        $this->tolak(fn () => app(UbahTanggalRealisasi::class)->handle($p, $diterima, ['tanggal_mulai' => '2026-03-05', 'tanggal_selesai' => '2026-03-25', 'alasan' => 'Menyesuaikan jadwal pemeriksaan.', 'diinput_oleh' => 1]));
        $this->tolak(fn () => app(UnggahDokumenSpt::class)->handle($p, $diterima, ['diinput_oleh' => 1], UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')));
        $this->tolak(fn () => app(TandaiSelesai::class)->handle($p, $diterima, ['diinput_oleh' => 1, 'konfirmasi' => true]));
        $this->tolak(fn () => app(SimpanKendala::class)->handle($p, $diterima, $kendala));
        $this->tolak(fn () => app(SimpanKendala::class)->handle($p, $selesai, $kendala));
        $this->tolak(fn () => app(SimpanSpt::class)->handle($p, $revisi, ['uraian_untuk' => 'Diubah pimpinan.'], 'simpan'));
        $this->tolak(fn () => app(SimpanSpt::class)->handle($p, $draf, ['uraian_untuk' => 'Diubah pimpinan.'], 'simpan'));
        $this->tolak(fn () => app(TerimaPengajuan::class)->handle($p, $menunggu));
        $this->tolak(fn () => app(MintaRevisi::class)->handle($p, $menunggu, 'Mohon perbaiki uraian penugasan.'));
        $this->tolak(fn () => app(TolakPengajuan::class)->handle($p, $menunggu, 'Tidak sesuai dengan ketentuan.'));
        $this->tolak(fn () => app(BatalkanNomor::class)->handle($p, $diterima, 'Salah nomor, dibatalkan pimpinan.'));
        $this->tolak(fn () => app(CatatNomorManual::class)->handle($p, [
            'irban_id' => $diterima->irban_id, 'klasifikasi_id' => Klasifikasi::where('kode', 'KHUSUS')->value('id'), 'uraian_untuk' => 'Mendesak',
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20', 'alasan' => 'Penugasan mendesak sekali.',
        ]));
    }

    public function test_detail_page_gives_pimpinan_no_irban_actions(): void
    {
        $spt = Spt::factory()->diterima()->create();

        $html = Livewire::actingAs($this->pimpinan)->test(Detail::class, ['spt' => $spt])->assertOk()->html();
        foreach (['Ubah Tanggal', 'Unggah SPT Final', 'Tandai Selesai', 'Isi Kendala', 'Ubah Kendala'] as $teks) {
            $this->assertStringNotContainsString($teks, $html);
        }
        foreach (['tanggal', 'final', 'kendala', 'selesai'] as $modal) {
            Livewire::actingAs($this->pimpinan)->test(Detail::class, ['spt' => $spt])->call('buka', $modal)->assertForbidden();
        }
    }
}
