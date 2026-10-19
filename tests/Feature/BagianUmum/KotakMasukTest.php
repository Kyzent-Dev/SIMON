<?php

namespace Tests\Feature\BagianUmum;

use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Livewire\BagianUmum\Pengajuan;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Objek;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Support\Navigation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Kotak Masuk Pengajuan (design/dashboard/bagian-umum.md "Inbox"). */
class KotakMasukTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private Irban $irban3;

    private User $bu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 10:00', 'Asia/Jakarta'));
        $this->irban = Irban::factory()->create(['nama' => 'Irban II', 'kode_nomor' => 'INSPEKTORAT-II']);
        $this->irban3 = Irban::factory()->create(['nama' => 'Irban III', 'kode_nomor' => 'INSPEKTORAT-III']);
        $this->bu = User::factory()->create(['role' => Role::BagianUmum]);
    }

    /** Submitted $hari days ago (Asia/Jakarta), oldest first in the Menunggu queue. */
    private function menunggu(string $uraian, int $hari = 0, ?Irban $irban = null): Spt
    {
        return Spt::factory()->menungguNomor()->create([
            'irban_id' => ($irban ?? $this->irban)->id, 'uraian_untuk' => $uraian, 'diajukan_pada' => now()->subDays($hari),
        ]);
    }

    private function inbox()
    {
        return Livewire::actingAs($this->bu)->test(Pengajuan::class);
    }

    public function test_route_nav_and_other_roles_forbidden(): void
    {
        $this->actingAs($this->bu)->get('/bagian-umum/pengajuan')->assertOk()->assertSee('Kotak Masuk Pengajuan')
            ->assertSee('Pintasan keyboard')->assertSee('Semua pengajuan sudah diproses.');
        $links = collect(Navigation::links(Role::BagianUmum));
        $this->assertSame(['label' => 'Kotak Masuk Pengajuan', 'route' => 'bagian-umum.pengajuan', 'icon' => 'inbox'], $links->firstWhere('route', 'bagian-umum.pengajuan'));

        foreach ([Role::Admin, Role::Pimpinan, Role::Irban] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/bagian-umum/pengajuan')->assertForbidden();
            Livewire::actingAs($u)->test(Pengajuan::class)->assertForbidden();
        }
    }

    public function test_tabs_with_counts_and_draf_never_listed(): void
    {
        $this->menunggu('Menunggu satu', 3);
        $this->menunggu('Menunggu dua', 1);
        Spt::factory()->perluRevisi()->create(['irban_id' => $this->irban->id, 'uraian_untuk' => 'Revisi satu']);
        Spt::factory()->diterima()->create(['irban_id' => $this->irban->id, 'uraian_untuk' => 'Diterima satu']);
        Spt::factory()->ditolak()->create(['irban_id' => $this->irban->id, 'uraian_untuk' => 'Ditolak satu']);
        $draf = Spt::factory()->create(['irban_id' => $this->irban->id, 'uraian_untuk' => 'Rahasia draf']);

        $c = $this->inbox()->assertSet('tab', 'menunggu_nomor')
            ->assertSee('Menunggu (2)')->assertSee('Perlu Revisi (1)')->assertSee('Diterima (1)')->assertSee('Ditolak (1)')
            ->assertSeeInOrder(['Menunggu satu', 'Menunggu dua'])->assertSee('3 hari')->assertDontSee('Revisi satu')->assertDontSee('Rahasia draf')
            ->assertSeeHtml('role="listbox"');
        foreach (['perlu_revisi' => 'Revisi satu', 'diterima' => 'Diterima satu', 'ditolak' => 'Ditolak satu'] as $tab => $teks) {
            $c->set('tab', $tab)->assertSee($teks)->assertDontSee('Menunggu satu')->assertDontSee('Rahasia draf');
        }

        $c->call('pilihSpt', $draf->id)->assertNotFound();
        $this->inbox()->set('pilih', $draf->id)->assertDontSee('Rahasia draf');
    }

    public function test_filters_irban_and_date_apply_to_list_and_counts(): void
    {
        $this->menunggu('Lama dua', 10);
        $this->menunggu('Baru dua', 0);
        $this->menunggu('Baru tiga', 0, $this->irban3);

        $this->inbox()->set('irban', (string) $this->irban3->id)->assertSee('Menunggu (1)')->assertSee('Baru tiga')->assertDontSee('Baru dua')
            ->set('irban', '')->set('dari', '2026-10-15')->assertSee('Menunggu (2)')->assertDontSee('Lama dua')
            ->set('dari', '')->set('sampai', '2026-10-12')->assertSee('Menunggu (1)')->assertSee('Lama dua')->assertDontSee('Baru');
    }

    public function test_selection_renders_detail(): void
    {
        $ppj = Pegawai::factory()->create(['nama_bergelar' => 'Andi Saputra, S.E.']);
        $ketua = Pegawai::factory()->create(['nama_bergelar' => 'Budi Santoso']);
        $anggota = Pegawai::factory()->create(['nama_bergelar' => 'Citra Lestari']);
        $spt = $this->menunggu('Melaksanakan Reviu LKjIP pada Dinas PUPR.');
        $spt->forceFill(['ppj_pegawai_id' => $ppj->id, 'catatan_bagian_umum' => 'Mohon segera, jadwal mendesak.',
            'jenis_penugasan_id' => JenisPenugasan::factory()->create(['nama' => 'Reviu LKjIP'])->id])->save();
        $spt->objek()->attach(Objek::factory()->create(['nama' => 'Dinas PUPR'])->id);
        $spt->personel()->createMany([
            ['pegawai_id' => $anggota->id, 'peran' => 'anggota', 'urutan' => 2, 'sumber' => 'tambahan'],
            ['pegawai_id' => $ketua->id, 'peran' => 'ketua', 'urutan' => 1, 'sumber' => 'tambahan'],
        ]);
        $dok = $spt->dokumen()->create(['jenis' => 'draf', 'path' => 'spt/x.pdf', 'nama_asli' => 'draf-spt.pdf', 'ukuran' => 2048, 'sha256' => str_repeat('a', 64), 'diunggah_oleh' => $this->bu->id]);

        $this->inbox()->call('pilihSpt', $spt->id)->assertSet('pilih', $spt->id)
            ->assertSee('Andi Saputra, S.E.')->assertSee('Mohon segera, jadwal mendesak.')->assertSee('Dinas PUPR')->assertSee('Tahun Anggaran 2025')
            ->assertSee('Durasi: 19 hari')->assertSee('2–20 Maret 2026')->assertSee('Non-PKPT')
            ->assertSeeInOrder(['Budi Santoso', 'Ketua', 'Citra Lestari', 'Anggota'])
            ->assertSee(route('spt.dokumen', [$spt, $dok]))->assertSee('draf-spt.pdf')
            ->assertSee('Terima')->assertSee('Perlu Revisi')->assertSee('Tolak')
            ->assertSeeHtml('aria-selected="true"');
    }

    public function test_terima_modal_shows_preview_then_issues_number_and_advances(): void
    {
        $a = $this->menunggu('Pertama', 3);
        $b = $this->menunggu('Kedua', 2);

        $this->inbox()->call('pilihSpt', $a->id)->call('bukaTerima')->assertSet('showTerima', true)
            ->assertSee('700/1/INSPEKTORAT-II/SPT/2026')->assertSee('Nomor berikutnya dalam register 2026')->assertSee('Terbitkan Nomor')
            ->call('terima')
            ->assertDispatched('toast', type: 'success', message: 'Nomor 700/1/INSPEKTORAT-II/SPT/2026 diterbitkan')
            ->assertSet('showTerima', false)->assertSet('pilih', $b->id)->assertSee('Menunggu (1)')
            ->call('bukaTerima')->assertSee('700/2/INSPEKTORAT-II/SPT/2026')->call('terima')
            ->assertSet('pilih', null)->assertSee('Semua pengajuan sudah diproses.');

        $this->assertSame([1, 2], [$a->refresh()->nomor_urut, $b->refresh()->nomor_urut]);
    }

    public function test_revisi_and_tolak_require_catatan(): void
    {
        $a = $this->menunggu('Pertama', 3);
        $b = $this->menunggu('Kedua', 2);

        $c = $this->inbox()->call('pilihSpt', $a->id)->call('bukaCatatan', 'revisi')->assertSet('showCatatan', true)
            ->assertSee('Catatan akan dikirim ke Irban II')
            ->set('catatan', 'pendek')->call('kirimCatatan')->assertHasErrors(['catatan'])->assertSee('Catatan minimal 10 karakter.')
            ->set('catatan', 'Periode pemeriksaan belum tepat.')->call('kirimCatatan')->assertHasNoErrors()
            ->assertSet('showCatatan', false)->assertSet('catatan', '')->assertSet('pilih', $b->id)->assertDispatched('toast');
        $this->assertSame(StatusPengajuan::PerluRevisi, $a->refresh()->status_pengajuan);

        $c->call('bukaCatatan', 'tolak')->set('catatan', 'Sudah ada SPT untuk objek ini.')->call('kirimCatatan');
        $this->assertSame(StatusPengajuan::Ditolak, $b->refresh()->status_pengajuan);

        $this->inbox()->call('pilihSpt', $a->id)->call('bukaCatatan', 'hapus')->assertSet('showCatatan', false);
    }

    public function test_concurrent_conflict_shows_alert_and_refresh(): void
    {
        $spt = $this->menunggu('Pertama');
        $c = $this->inbox()->call('pilihSpt', $spt->id)->call('bukaTerima');
        $spt->forceFill(['status_pengajuan' => StatusPengajuan::Ditolak])->save(); // another user decided meanwhile

        $c->call('terima')->assertSet('showTerima', false)->assertSet('konflik', true)->assertSee('Pengajuan ini sudah diproses oleh pengguna lain.')
            ->assertNotDispatched('toast')
            ->call('muatUlang')->assertSet('konflik', false)->assertDontSee('Pengajuan ini sudah diproses oleh pengguna lain.');
        $this->assertNull($spt->refresh()->nomor_lengkap);

        // Opening a decision on an SPT that was pending in this view but is not any more also reports the conflict instead of opening the modal.
        $baru = $this->menunggu('Kedua');
        $d = $this->inbox()->call('pilihSpt', $baru->id);
        $baru->forceFill(['status_pengajuan' => StatusPengajuan::Ditolak])->save();
        $d->call('bukaTerima')->assertSet('showTerima', false)->assertSet('konflik', true)
            ->call('muatUlang')->call('bukaCatatan', 'tolak')->assertSet('showCatatan', false)->assertSet('konflik', true);
    }

    public function test_decision_keys_on_a_processed_spt_do_nothing_and_raise_no_conflict(): void
    {
        $spt = Spt::factory()->diterima()->create(['irban_id' => $this->irban->id]);

        $this->inbox()->set('tab', 'diterima')->call('pilihSpt', $spt->id)
            ->call('bukaTerima')->assertSet('showTerima', false)->assertSet('konflik', false)
            ->call('bukaCatatan', 'revisi')->assertSet('showCatatan', false)->assertSet('konflik', false)
            ->call('bukaCatatan', 'tolak')->assertSet('showCatatan', false)->assertSet('konflik', false);
    }

    public function test_date_filter_uses_jakarta_days(): void
    {
        foreach (['2026-10-14', '2026-10-15', '2026-10-16'] as $hari) {
            Spt::factory()->menungguNomor()->create([
                'irban_id' => $this->irban->id, 'uraian_untuk' => "Malam {$hari}", 'diajukan_pada' => CarbonImmutable::parse("{$hari} 18:00", 'Asia/Jakarta'),
            ]);
        }

        $this->inbox()->set('dari', '2026-10-15')->set('sampai', '2026-10-15')
            ->assertSee('Menunggu (1)')->assertSee('Malam 2026-10-15')->assertDontSee('Malam 2026-10-14')->assertDontSee('Malam 2026-10-16');
    }

    public function test_keyboard_markup_and_shortcut_guard(): void
    {
        $spt = $this->menunggu('Pertama');

        // The Alpine handler (resources/js/kotak-masuk.js) ignores keys typed in fields; the markup wires it to the list + detail only.
        $this->inbox()->call('pilihSpt', $spt->id)
            ->assertSeeHtml('x-data="kotakMasuk"')->assertSeeHtml('x-on:keydown.window="tombol($event)"')
            ->assertSeeHtml('aria-activedescendant="spt-opsi-'.$spt->id.'"')->assertSeeHtml('id="spt-opsi-'.$spt->id.'"')
            ->assertSee('Bantuan pintasan')->assertSee('Pintasan keyboard');
        $js = file_get_contents(resource_path('js/kotak-masuk.js'));
        $this->assertStringContainsString('isContentEditable', $js);
        $this->assertMatchesRegularExpression('/INPUT\|TEXTAREA\|SELECT/', $js);
    }
}
