<?php

namespace Tests\Feature\Spt;

use App\Enums\KejadianSpt;
use App\Enums\PeranTim;
use App\Enums\Role;
use App\Livewire\BagianUmum\Pengajuan;
use App\Livewire\BagianUmum\Register;
use App\Livewire\Irban\SptSaya;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\SptDokumen;
use App\Models\SptKendala;
use App\Models\SptRiwayat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DetailSptTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-03-25 10:00', 'Asia/Jakarta'));
        $this->irban = Irban::factory()->create(['nama' => 'Irban I']);
        $this->user = $this->irbanUser($this->irban);
    }

    private function irbanUser(Irban $irban): User
    {
        $u = User::factory()->irban()->create();
        $u->forceFill(['irban_id' => $irban->id])->save();

        return $u;
    }

    private function spt(array $attrs = [], string $state = 'diterima'): Spt
    {
        return Spt::factory()->{$state}()->create(['irban_id' => $this->irban->id, ...$attrs]);
    }

    private function lihat(Spt $spt, ?User $u = null)
    {
        return $this->actingAs($u ?? $this->user)->get(route('spt.show', $spt));
    }

    public function test_access_rules(): void
    {
        foreach (['create', 'menungguNomor', 'perluRevisi', 'ditolak', 'diterima', 'selesai', 'dibatalkan'] as $state) {
            $spt = $state === 'create' ? Spt::factory()->create(['irban_id' => $this->irban->id]) : $this->spt([], $state);
            $this->lihat($spt)->assertOk();
        }
        $draf = Spt::factory()->create(['irban_id' => $this->irban->id]);
        $terkirim = $this->spt();
        foreach ([User::factory()->bagianUmum()->create(), User::factory()->pimpinan()->create(), User::factory()->admin()->create()] as $u) {
            $this->lihat($terkirim, $u)->assertOk();
            $this->lihat($draf, $u)->assertForbidden();
        }
        $this->lihat($terkirim, $this->irbanUser(Irban::factory()->create()))->assertForbidden();
        auth()->logout();
        $this->get(route('spt.show', $terkirim))->assertRedirect(route('login'));
    }

    public function test_header_number_status_and_flags(): void
    {
        $spt = $this->spt(['nomor_lengkap' => '700/12/INSPEKTORAT-I/SPT/2026', 'tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-05']);
        $this->lihat($spt)->assertSee('700/12/INSPEKTORAT-I/SPT/2026')->assertSee('Salin nomor')
            ->assertSee('Penyusunan Laporan')->assertSee('Non-PKPT')->assertSee('SPT Final Belum Diupload')->assertSee('Terlambat')
            ->assertSee('Irban I')->assertSee('KHUSUS');

        $this->lihat($this->spt([], 'menungguNomor'))->assertSee('Nomor belum terbit')->assertSee('Menunggu Nomor');
    }

    public function test_ringkasan_values_and_kesesuaian_variants(): void
    {
        $alert = 'SPT ini Tidak Sesuai PKPT. Isi kendala untuk menjelaskan penyebabnya.';
        $item = PkptItem::factory()->create(['irban_id' => $this->irban->id, 'tanggal_mulai' => '2026-03-01', 'tanggal_selesai' => '2026-03-31']);
        $sesuai = $this->spt(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-03-03', 'tanggal_selesai' => '2026-03-21', 'periode_diperiksa' => 'TA 2025']);
        $this->lihat($sesuai)->assertSee('3–21 Maret 2026 · 19 hari')->assertSee('Sesuai jadwal PKPT (1–31 Maret 2026)')
            ->assertSee('TA 2025')->assertSee('PKPT '.$item->pkpt->tahun)->assertDontSee('Tidak Sesuai PKPT')
            ->assertDontSee($alert);

        // The Ringkasan flag itself (the label alone also appears in the header flags and in the kendala alert).
        $flag = '#<dd class="tabular-nums">\s*(?:<!--\[if BLOCK\]><!\[endif\]-->)?\s*<span class="inline-flex flex-wrap items-center gap-2"><span class="[^"]*bg-flag-tidak_sesuai_pkpt-bg[^"]*">.*?Tidak Sesuai PKPT\s*</span>\s*<span>\(1–31 Maret 2026\)</span></span>#su';
        $tidak = $this->spt(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-03-20', 'tanggal_selesai' => '2026-04-10']);
        $this->assertMatchesRegularExpression($flag, $this->lihat($tidak)->assertSee($alert)->getContent());
        $this->assertDoesNotMatchRegularExpression($flag, $this->lihat($sesuai)->getContent()); // control

        $this->lihat($this->spt())->assertSee('Tidak dinilai')->assertSee('Non-PKPT')->assertDontSee($alert);

        // Before the number is issued the flag is not raised (PRD 9.3, PenandaSpt), so the summary states the range without it.
        foreach (['menungguNomor', 'perluRevisi'] as $state) {
            $belum = $this->spt(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-03-20', 'tanggal_selesai' => '2026-04-10'], $state);
            $this->lihat($belum)->assertSee('Di luar jadwal PKPT (1–31 Maret 2026)')->assertDontSee('Tidak Sesuai PKPT');
        }
    }

    public function test_personel_in_order_and_documents_with_links(): void
    {
        $spt = $this->spt();
        foreach ([[3, 'Citra Anggota', PeranTim::Anggota], [1, 'Andi Dalnis', PeranTim::SupervisorDalnis], [2, 'Budi Ketua', PeranTim::Ketua]] as [$u, $nama, $peran]) {
            $spt->personel()->create(['pegawai_id' => Pegawai::factory()->create(['nama_bergelar' => $nama])->id, 'peran' => $peran, 'urutan' => $u, 'sumber' => 'tambahan']);
        }
        $penginput = Pegawai::factory()->create(['nama_bergelar' => 'Dewi Penginput']);
        $dok = SptDokumen::create(['spt_id' => $spt->id, 'jenis' => 'final', 'path' => 'spt/x.pdf', 'nama_asli' => 'spt-final.pdf', 'ukuran' => 2 * 1024 * 1024,
            'sha256' => str_repeat('a', 64), 'diunggah_oleh' => $this->user->id, 'diinput_oleh' => $penginput->id]);

        $this->lihat($spt)->assertSeeInOrder(['Andi Dalnis', 'Budi Ketua', 'Citra Anggota'])
            ->assertSee('spt-final.pdf')->assertSee('SPT final')->assertSee('2,0 MB')->assertSee('Diinput oleh Dewi Penginput')
            ->assertSee(route('spt.dokumen', [$spt, $dok]))->assertSee(route('spt.dokumen', [$spt, $dok]).'?unduh=1');
    }

    public function test_riwayat_in_order_with_diinput_oleh_and_kendala_states(): void
    {
        $spt = $this->spt();
        $p = Pegawai::factory()->create(['nama_bergelar' => 'Eko Penginput']);
        SptRiwayat::create(['spt_id' => $spt->id, 'kejadian' => KejadianSpt::DIAJUKAN, 'oleh' => $this->user->id, 'diinput_oleh' => $p->id]);
        SptRiwayat::create(['spt_id' => $spt->id, 'kejadian' => KejadianSpt::DITERIMA, 'oleh' => $this->user->id]);
        SptRiwayat::create(['spt_id' => $spt->id, 'kejadian' => KejadianSpt::TANGGAL_DIUBAH, 'oleh' => $this->user->id, 'alasan' => 'Jadwal objek bergeser.',
            'nilai_lama' => ['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20'], 'nilai_baru' => ['tanggal_mulai' => '2026-03-03', 'tanggal_selesai' => '2026-03-21']]);

        $this->lihat($spt)->assertSeeInOrder(['Diajukan', 'Nomor diterbitkan', 'Tanggal realisasi diubah'])
            ->assertSee('· Diinput oleh Eko Penginput')->assertSee('Jadwal objek bergeser.')->assertSee('2–20 Maret 2026 → 3–21 Maret 2026')
            ->assertSee('Belum ada kendala.');

        SptKendala::create(['spt_id' => $spt->id, 'kategori' => 'Objek tidak siap', 'uraian' => 'Kantor objek sedang direnovasi.', 'dibuat_oleh' => $this->user->id]);
        $this->lihat($spt)->assertSee('Objek tidak siap')->assertSee('Kantor objek sedang direnovasi.')->assertDontSee('Belum ada kendala.');
    }

    public function test_action_buttons_only_for_laksanakan_and_ubah_only_for_editable(): void
    {
        $spt = $this->spt();
        $this->lihat($spt)->assertSee('Ubah Tanggal')->assertSee('Unggah SPT Final')->assertSee('Isi Kendala')->assertSee('Tandai Selesai');
        SptKendala::create(['spt_id' => $spt->id, 'kategori' => 'Lainnya', 'uraian' => 'Uraian kendala yang cukup.', 'dibuat_oleh' => $this->user->id]);
        $this->lihat($spt)->assertSee('Ubah Kendala')->assertDontSee('Isi Kendala');

        $this->lihat($spt, User::factory()->bagianUmum()->create())->assertDontSee('Ubah Tanggal')->assertDontSee('Tandai Selesai');
        $this->lihat($this->spt([], 'selesai'))->assertDontSee('Ubah Tanggal')->assertDontSee('Unggah SPT Final');

        $draf = Spt::factory()->create(['irban_id' => $this->irban->id]);
        $this->lihat($draf)->assertSee(route('irban.spt.ubah', $draf))->assertDontSee('Ubah Tanggal');
        $revisi = $this->spt([], 'perluRevisi');
        $this->lihat($revisi)->assertSee(route('irban.spt.ubah', $revisi));
        $this->lihat($spt)->assertDontSee(route('irban.spt.ubah', $spt));
    }

    public function test_links_from_spt_saya_kotak_masuk_and_register(): void
    {
        $spt = $this->spt(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-05']);
        Livewire::actingAs($this->user)->test(SptSaya::class)->assertSee(route('spt.show', $spt))
            ->assertSee('Penanda')->assertSee('SPT Final Belum Diupload')->assertSee('Non-PKPT');

        $bu = User::factory()->create(['role' => Role::BagianUmum]);
        $menunggu = $this->spt([], 'menungguNomor');
        Livewire::actingAs($bu)->test(Pengajuan::class)->call('pilihSpt', $menunggu->id)
            ->assertSee('Buka halaman detail')->assertSee(route('spt.show', $menunggu));
        Livewire::actingAs($bu)->test(Register::class)->assertSee(route('spt.show', $spt));
    }

    public function test_nav_highlights_spt_saya_and_register(): void
    {
        $spt = $this->spt();
        $this->assertSame(['SPT Saya'], $this->aktif($this->lihat($spt)->getContent()));
        $this->assertSame(['Register Nomor SPT'], $this->aktif($this->lihat($spt, User::factory()->bagianUmum()->create())->getContent()));
    }

    /** Labels of the sidebar links rendered as current. */
    private function aktif(string $html): array
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML($html);
        $hasil = [];
        foreach ((new \DOMXPath($dom))->query('//a[@data-nav-link][@data-current]') as $a) {
            $hasil[] = trim($a->textContent);
        }

        return $hasil;
    }
}
