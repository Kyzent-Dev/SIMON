<?php

namespace Tests\Feature\Spt;

use App\Actions\Spt\UnggahDokumenSpt;
use App\Livewire\Spt\Detail;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Klasifikasi;
use App\Models\Pegawai;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\SptKendala;
use App\Models\User;
use App\Support\DiinputOleh;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 5 Task 4: the four action modals on the SPT detail page call the Task 2 actions. */
class DetailAksiTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private User $user;

    private Pegawai $penginput;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::parse('2026-03-25 10:00', 'Asia/Jakarta'));
        $this->irban = Irban::factory()->create();
        $this->user = $this->irbanUser($this->irban);
        $this->penginput = Pegawai::factory()->create(['nama_bergelar' => 'Budi Penginput']);
        IrbanTahunan::factory()->create(['irban_id' => $this->irban->id, 'tahun' => 2026, 'ppj_pegawai_id' => $this->penginput->id]);
    }

    private function irbanUser(Irban $irban): User
    {
        $u = User::factory()->irban()->create();
        $u->forceFill(['irban_id' => $irban->id])->save();

        return $u;
    }

    private function spt(string $kode = 'KHUSUS', array $attrs = []): Spt
    {
        return Spt::factory()->diterima()->create([
            'irban_id' => $this->irban->id, 'klasifikasi_id' => Klasifikasi::where('kode', $kode)->value('id'),
            'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20', ...$attrs,
        ]);
    }

    private function pdf(string $nama = 'x.pdf'): UploadedFile
    {
        return UploadedFile::fake()->create($nama, 10, 'application/pdf');
    }

    private function detail(Spt $spt, ?User $u = null)
    {
        return Livewire::actingAs($u ?? $this->user)->test(Detail::class, ['spt' => $spt]);
    }

    public function test_buka_modal_mengisi_nilai_awal_dan_penginput_terakhir(): void
    {
        $spt = $this->spt();
        session()->put("diinput_oleh.{$this->irban->id}", $this->penginput->id);

        $this->detail($spt)->call('buka', 'tanggal')
            ->assertSet('modal', 'tanggal')->assertSet('tanggal_mulai', '2026-03-02')->assertSet('tanggal_selesai', '2026-03-20')
            ->assertSet('diinput_oleh', $this->penginput->id)->assertDispatched('open-modal', name: 'aksi-tanggal')
            ->assertSee('Ubah Tanggal Realisasi')->assertSee('Simpan Perubahan')->assertSee('Ganti penginput, saat ini Budi Penginput');

        $this->detail($spt)->call('buka', 'lain')->assertSet('modal', '')->assertNotDispatched('open-modal');
    }

    public function test_ubah_tanggal_happy_path_dan_validasi_inline(): void
    {
        $spt = $this->spt();
        $this->detail($spt)->call('buka', 'tanggal')
            ->set('tanggal_mulai', '2026-03-05')->set('tanggal_selesai', '2026-03-04')->set('alasan', 'pendek')
            ->call('simpanTanggal')
            ->assertHasErrors(['tanggal_selesai', 'alasan', 'diinput_oleh'])
            ->assertSee('Tanggal selesai tidak boleh sebelum tanggal mulai.')->assertSee('Alasan minimal 10 karakter.')
            ->assertSee('Pilih nama penginput terlebih dahulu')->assertNotDispatched('toast');

        $this->detail($spt)->call('buka', 'tanggal')->set('alasan', 'Menyesuaikan jadwal objek.')->set('diinput_oleh', $this->penginput->id)
            ->call('simpanTanggal')->assertHasErrors('tanggal_mulai')->assertSee('Tanggal realisasi tidak berubah.');

        $this->detail($spt)->call('buka', 'tanggal')
            ->set('tanggal_mulai', '2026-03-05')->set('tanggal_selesai', '2026-03-25')->set('alasan', 'Menyesuaikan jadwal objek.')
            ->set('diinput_oleh', $this->penginput->id)->call('simpanTanggal')
            ->assertHasNoErrors()->assertSet('modal', '')->assertDispatched('close-modal', name: 'aksi-tanggal')
            ->assertDispatched('toast', type: 'success', message: 'Tanggal realisasi diperbarui');
        $this->assertSame('2026-03-05', $spt->fresh()->tanggal_mulai->toDateString());
        $this->assertSame($this->penginput->id, DiinputOleh::terakhir($this->user));
    }

    public function test_unggah_spt_final(): void
    {
        $spt = $this->spt();
        $this->detail($spt)->call('buka', 'final')->assertSee('PDF, maksimal 10 MB')->assertSee('Unggah')
            ->set('diinput_oleh', $this->penginput->id)->call('unggahFinal')
            ->assertHasErrors('berkas')->assertSee('Pilih berkas PDF.');

        $this->detail($spt)->call('buka', 'final')->set('berkas', UploadedFile::fake()->create('x.docx', 10, 'application/msword'))
            ->set('diinput_oleh', $this->penginput->id)->call('unggahFinal')->assertSee('Dokumen harus PDF dan maksimal 10 MB.');

        $this->detail($spt)->call('buka', 'final')->set('berkas', $this->pdf('final.pdf'))->set('diinput_oleh', $this->penginput->id)
            ->call('unggahFinal')->assertHasNoErrors()->assertSet('berkas', null)
            ->assertDispatched('toast', type: 'success', message: 'SPT final diunggah')->assertSee('final.pdf');
        $this->assertSame(1, $spt->dokumen()->where('jenis', 'final')->count());
    }

    public function test_tandai_selesai_lhp_lengkap(): void
    {
        $spt = $this->spt('KHUSUS');
        $this->detail($spt)->call('buka', 'selesai')->assertSee('Cover LHP')->assertSee('Nomor LHP')->assertSee('Tanggal LHP')
            ->assertDontSee('Saya menyatakan penugasan ini telah selesai.')
            ->set('diinput_oleh', $this->penginput->id)->set('tanggal_lhp', '2026-03-01')->call('tandaiSelesai')
            ->assertSee('Unggah cover LHP (PDF).')->assertSee('Isi nomor LHP.')->assertSee('Tanggal LHP tidak boleh sebelum tanggal mulai SPT.');

        $this->detail($spt)->call('buka', 'selesai')->set('berkas', $this->pdf('cover.pdf'))->set('nomor_lhp', 'LHP-01')->set('tanggal_lhp', '2026-03-24')
            ->set('diinput_oleh', $this->penginput->id)->call('tandaiSelesai')
            ->assertHasNoErrors()->assertDispatched('toast', type: 'success', message: 'SPT ditandai Selesai')
            ->assertDispatched('fokus-ke', id: 'judul-spt')
            ->assertDontSee('Unggah SPT Final')->assertDontSee('Ubah Tanggal');
        $spt->refresh();
        $this->assertNotNull($spt->ditandai_selesai_pada);
        $this->assertSame('LHP-01', $spt->nomor_lhp);
        $this->assertSame(1, $spt->dokumen()->where('jenis', 'cover_lhp')->count());
    }

    public function test_tandai_selesai_konfirmasi_dan_opsional(): void
    {
        $diklat = $this->spt('DIKLAT');
        $this->detail($diklat)->call('buka', 'selesai')->assertSee('Saya menyatakan penugasan ini telah selesai.')->assertDontSee('Cover LHP')
            ->set('diinput_oleh', $this->penginput->id)->call('tandaiSelesai')->assertSee('Centang konfirmasi bahwa penugasan telah selesai.')
            ->set('konfirmasi', true)->call('tandaiSelesai')->assertHasNoErrors();
        $this->assertNotNull($diklat->fresh()->ditandai_selesai_pada);

        $luar = $this->spt('LUAR_DAERAH');
        $this->detail($luar)->call('buka', 'selesai')->assertSee('Laporan/dokumen penyelesaian (opsional)')->assertSee('Nomor LHP (opsional)')
            ->assertDontSee('Saya menyatakan')->set('diinput_oleh', $this->penginput->id)->call('tandaiSelesai')->assertHasNoErrors();
        $this->assertNotNull($luar->fresh()->ditandai_selesai_pada);
    }

    public function test_bisa_memblokir_tandai_selesai(): void
    {
        $belum = $this->spt('KHUSUS', ['tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-10']);
        $this->detail($belum)->call('buka', 'selesai')->assertSee('SPT belum dilaksanakan.')
            ->assertSeeHtml('aria-describedby="selesai-blokir"')->assertSeeHtml('disabled="disabled"');

        $item = PkptItem::factory()->create(['irban_id' => $this->irban->id, 'tanggal_mulai' => '2026-01-05', 'tanggal_selesai' => '2026-01-20']);
        $tidakSesuai = $this->spt('KHUSUS', ['pkpt_item_id' => $item->id]);
        $this->detail($tidakSesuai)->call('buka', 'selesai')->assertSee('Isi kendala terlebih dahulu karena SPT Tidak Sesuai PKPT.');

        // A crafted call is still refused by the action and shown as a toast.
        $this->detail($belum)->call('buka', 'selesai')->set('diinput_oleh', $this->penginput->id)->call('tandaiSelesai')
            ->assertDispatched('toast', type: 'error', message: 'SPT belum dilaksanakan.')->assertSet('modal', '');
        $this->assertNull($belum->fresh()->ditandai_selesai_pada);
    }

    public function test_kendala_dari_alert_dan_ubah(): void
    {
        $item = PkptItem::factory()->create(['irban_id' => $this->irban->id, 'tanggal_mulai' => '2026-01-05', 'tanggal_selesai' => '2026-01-20']);
        $spt = $this->spt('KHUSUS', ['pkpt_item_id' => $item->id]);
        $this->detail($spt)->assertSeeHtml("wire:click=\"buka('kendala')\"")
            ->call('buka', 'kendala')->assertSee('Simpan Kendala')->assertSee('Objek tidak siap')
            ->set('uraian', 'pendek')->call('simpanKendala')
            ->assertSee('Pilih kategori kendala.')->assertSee('Uraian kendala minimal 10 karakter.')->assertSee('Pilih nama penginput terlebih dahulu')
            ->set('kategori', 'Objek tidak siap')->set('uraian', 'Objek belum siap diperiksa.')->set('diinput_oleh', $this->penginput->id)
            ->call('simpanKendala')->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Kendala disimpan')->assertDispatched('fokus-ke', id: 'aksi-kendala')
            ->assertSee('Ubah Kendala')->assertDontSee('Isi kendala untuk menjelaskan penyebabnya.');
        $this->assertSame('Objek tidak siap', $spt->kendala()->value('kategori'));

        $this->detail($spt)->call('buka', 'kendala')->assertSet('kategori', 'Objek tidak siap')->assertSet('uraian', 'Objek belum siap diperiksa.');
    }

    public function test_spt_selesai_tanpa_tombol_dan_panggilan_ditoast(): void
    {
        $spt = $this->spt('KHUSUS', ['ditandai_selesai_pada' => now()]);
        $this->detail($spt)->assertDontSee('Tandai Selesai')->assertDontSee('Unggah SPT Final')
            ->set('diinput_oleh', $this->penginput->id)->set('kategori', 'Lainnya')->set('uraian', 'Uraian yang cukup panjang.')
            ->call('simpanKendala')->assertDispatched('toast', type: 'error', message: 'SPT ini sudah Selesai dan tidak dapat diubah.');
        $this->assertNull($spt->kendala()->first());
    }

    public function test_bu_dan_pimpinan_tanpa_tombol_dan_ditolak(): void
    {
        $spt = $this->spt();
        foreach ([User::factory()->bagianUmum()->create(), User::factory()->pimpinan()->create()] as $u) {
            $this->detail($spt, $u)->assertDontSee('Tandai Selesai')->assertDontSee('Unggah SPT Final')->assertDontSee('Ubah Tanggal');
            foreach (['buka' => ['tanggal'], 'simpanTanggal' => [], 'unggahFinal' => [], 'tandaiSelesai' => [], 'simpanKendala' => [], 'gantiPenginput' => []] as $m => $args) {
                $this->detail($spt, $u)->call($m, ...$args)->assertForbidden();
            }
        }
        $this->assertNull($spt->fresh()->ditandai_selesai_pada);
    }

    public function test_irban_lain_tidak_bisa_memanggil_aksi_dengan_id_dipalsukan(): void
    {
        $milikSaya = $this->spt();
        $lain = Irban::factory()->create();
        $sptLain = Spt::factory()->diterima()->create(['irban_id' => $lain->id, 'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20']);

        // The id is #[Locked]: it cannot be swapped to another irban's SPT.
        try {
            $this->detail($milikSaya)->set('sptId', $sptLain->id);
            $this->fail('sptId must be locked');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('locked', strtolower($e->getMessage()));
        }

        // A request replayed by another irban's account against a valid snapshot is refused by every method.
        $orangLain = $this->irbanUser($lain);
        foreach (['buka' => ['kendala'], 'simpanTanggal' => [], 'unggahFinal' => [], 'tandaiSelesai' => [], 'simpanKendala' => [], 'gantiPenginput' => []] as $m => $args) {
            $c = $this->detail($milikSaya);
            $this->actingAs($orangLain);
            $c->call($m, ...$args)->assertForbidden();
            $this->actingAs($this->user);
        }
        $this->assertNull($milikSaya->kendala()->first());
    }

    public function test_dokumen_lihat_memberi_tanda_tab_baru(): void
    {
        $spt = $this->spt();
        app(UnggahDokumenSpt::class)->handle($this->user, $spt, ['diinput_oleh' => $this->penginput->id], $this->pdf('final.pdf'));
        $this->detail($spt)->assertSeeHtml('<span class="sr-only"> final.pdf (tab baru)</span>');
        $this->assertSame(0, SptKendala::count());
    }
}
