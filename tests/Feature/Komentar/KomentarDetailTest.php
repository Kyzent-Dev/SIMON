<?php

namespace Tests\Feature\Komentar;

use App\Enums\StatusPermintaan;
use App\Livewire\Spt\Komentar as KomentarComponent;
use App\Models\IrbanTahunan;
use App\Models\Komentar;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 7 Task 3: the Komentar section on the SPT detail page. */
class KomentarDetailTest extends TestCase
{
    use RefreshDatabase;

    private Spt $spt;

    private User $pimpinan;

    private User $irban;

    private User $bu;

    private User $admin;

    private Pegawai $penginput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-03-25 10:00', 'Asia/Jakarta'));
        $this->spt = Spt::factory()->diterima()->create();
        $this->pimpinan = User::factory()->pimpinan()->create(['name' => 'Ibu Inspektur']);
        $this->irban = User::factory()->irban()->create(['name' => 'Akun Irban']);
        $this->irban->forceFill(['irban_id' => $this->spt->irban_id])->save();
        $this->bu = User::factory()->bagianUmum()->create(['name' => 'Petugas Umum']);
        $this->admin = User::factory()->admin()->create();
        $this->penginput = Pegawai::factory()->create(['nama_bergelar' => 'Budi Penginput']);
        IrbanTahunan::factory()->create(['irban_id' => $this->spt->irban_id, 'tahun' => 2026, 'ppj_pegawai_id' => $this->penginput->id]);
    }

    private function root(array $attrs = [], bool $minta = false, ?Spt $spt = null): Komentar
    {
        $f = Komentar::factory();

        return ($minta ? $f->mintaPenjelasan() : $f)->create(['spt_id' => ($spt ?? $this->spt)->id, 'user_id' => $this->pimpinan->id, ...$attrs]);
    }

    private function balasIrban(Komentar $root, string $isi = 'Sudah kami tindak lanjuti.'): Komentar
    {
        return Komentar::factory()->balasanDari($root, $this->irban)->create(['isi' => $isi, 'diinput_oleh' => $this->penginput->id]);
    }

    private function lw(User $u)
    {
        return Livewire::actingAs($u)->test(KomentarComponent::class, ['spt' => $this->spt]);
    }

    public function test_every_role_sees_the_threads_on_the_detail_page(): void
    {
        $root = $this->root(['isi' => 'Mohon perhatikan jadwal.']);
        $this->balasIrban($root);

        foreach ([$this->pimpinan, $this->irban, $this->bu, $this->admin] as $u) {
            $this->actingAs($u)->get(route('spt.show', $this->spt))->assertOk()
                ->assertSeeLivewire(KomentarComponent::class)
                ->assertSeeInOrder(['Dokumen', 'id="judul-komentar"', 'Mohon perhatikan jadwal.', 'Sudah kami tindak lanjuti.'], false)
                ->assertSee('order-6 lg:order-none', false)
                ->assertSee('2 komentar')
                ->assertSee('Diinput oleh Budi Penginput');
        }
    }

    public function test_composer_only_for_pimpinan_and_balas_not_for_admin(): void
    {
        $this->root();

        $this->lw($this->pimpinan)->assertSee('Kirim komentar')->assertSee('Tandai sebagai Minta Penjelasan')->assertSee('Balas komentar Ibu Inspektur');
        foreach ([$this->irban, $this->bu] as $u) {
            $this->lw($u)->assertDontSee('Kirim komentar')->assertDontSee('Tandai sebagai Minta Penjelasan')->assertSee('Balas komentar Ibu Inspektur');
        }
        $this->lw($this->admin)->assertDontSee('Kirim komentar')->assertDontSee('Balas komentar');
        $this->lw($this->admin)->call('bukaBalas', Komentar::first()->id)->assertForbidden();
    }

    public function test_reply_form_has_diinput_oleh_footer_only_for_irban(): void
    {
        $root = $this->root();
        session()->put("diinput_oleh.{$this->spt->irban_id}", $this->penginput->id);

        $this->lw($this->irban)->call('bukaBalas', $root->id)
            ->assertSet('balasUntuk', $root->id)->assertSet('diinput_oleh', $this->penginput->id)
            ->assertSee('Kirim balasan')->assertSee('Diinput oleh')->assertSee('Ganti penginput, saat ini Budi Penginput')
            ->assertSee('aria-expanded="true"', false)
            ->assertDispatched('fokus-ke', id: 'balasan');

        $this->lw($this->bu)->call('bukaBalas', $root->id)->assertSee('Kirim balasan')->assertDontSee('Diinput oleh');

        $this->lw($this->bu)->call('bukaBalas', $root->id)->call('batalBalas')
            ->assertSet('balasUntuk', null)->assertDontSee('Kirim balasan')->assertDispatched('fokus-ke', id: "balas-{$root->id}");
    }

    public function test_kirim_komentar_calls_the_action(): void
    {
        $this->lw($this->pimpinan)->set('isi', '  Mohon dijelaskan.  ')->set('minta_penjelasan', true)->call('kirimKomentar')
            ->assertHasNoErrors()->assertSet('isi', '')->assertSet('minta_penjelasan', false)
            ->assertDispatched('toast', type: 'success', message: 'Komentar dikirim')
            ->assertDispatched('fokus-ke', id: 'komentar-'.Komentar::first()->id)
            ->assertSee('Permintaan Penjelasan · Terbuka');

        $k = Komentar::first();
        $this->assertSame('Mohon dijelaskan.', $k->isi);
        $this->assertSame(StatusPermintaan::Terbuka, $k->status_permintaan);

        $this->lw($this->pimpinan)->set('isi', '   ')->call('kirimKomentar')->assertHasErrors(['isi' => 'Tulis isi komentar.']);
        $this->assertSame(1, Komentar::count());
    }

    public function test_kirim_balasan_calls_the_action(): void
    {
        $root = $this->root();

        $this->lw($this->irban)->call('bukaBalas', $root->id)->set('balasan', 'Baik.')->call('kirimBalasan')
            ->assertHasErrors(['diinput_oleh' => 'Pilih nama penginput terlebih dahulu']);
        $this->lw($this->irban)->call('bukaBalas', $root->id)->call('kirimBalasan')->assertHasErrors(['balasan' => 'Tulis isi komentar.']);

        $this->lw($this->irban)->call('bukaBalas', $root->id)->set('balasan', 'Baik, Pak.')->set('diinput_oleh', $this->penginput->id)->call('kirimBalasan')
            ->assertHasNoErrors()->assertSet('balasUntuk', null)->assertSet('balasan', '')
            ->assertDispatched('toast', type: 'success', message: 'Balasan dikirim')
            ->assertDispatched('fokus-ke', id: "balas-{$root->id}")
            ->assertSee('Baik, Pak.');
        $this->assertSame($this->penginput->id, $root->balasan()->first()->diinput_oleh);

        $this->lw($this->bu)->call('bukaBalas', $root->id)->set('balasan', 'Dicatat.')->set('diinput_oleh', $this->penginput->id)->call('kirimBalasan')->assertHasNoErrors();
        $this->assertNull($root->balasan()->where('isi', 'Dicatat.')->first()->diinput_oleh);
    }

    public function test_tampered_ids_of_another_spt_become_a_toast_without_writing(): void
    {
        $asing = $this->root(['isi' => 'Utas SPT lain'], true, Spt::factory()->diterima()->create());
        $this->balasIrban($asing);
        $sebelum = Komentar::count();

        $this->lw($this->pimpinan)->call('bukaBalas', $asing->id)
            ->assertSet('balasUntuk', null)->assertDispatched('toast', type: 'error', message: 'Komentar yang dibalas tidak ditemukan.');

        $this->lw($this->pimpinan)->set('balasUntuk', $asing->id)->set('balasan', 'Masuk?')->call('kirimBalasan')
            ->assertDispatched('toast', type: 'error', message: 'Komentar yang dibalas tidak ditemukan.')
            ->assertDispatched('fokus-ke', id: 'judul-komentar');

        $this->lw($this->pimpinan)->call('tutup', $asing->id)
            ->assertDispatched('toast', type: 'error', message: 'Permintaan penjelasan tidak ditemukan.');

        $this->assertSame($sebelum, Komentar::count());
        $this->assertSame(StatusPermintaan::Terbuka, $asing->fresh()->status_permintaan);
    }

    public function test_minta_penjelasan_labels_and_tutup(): void
    {
        $root = $this->root(['isi' => 'Jelaskan keterlambatan.'], true);

        $this->lw($this->pimpinan)->assertSee('Permintaan Penjelasan · Terbuka')->assertSee('Menunggu balasan Irban')->assertDontSee('Tutup permintaan');
        $this->lw($this->pimpinan)->call('tutup', $root->id)
            ->assertDispatched('toast', type: 'error', message: 'Permintaan baru dapat ditutup setelah Irban membalas.')
            ->assertDispatched('fokus-ke', id: 'judul-komentar');

        $this->balasIrban($root);
        $this->lw($this->bu)->assertDontSee('Menunggu balasan Irban')->assertDontSee('Tutup permintaan');
        $this->lw($this->pimpinan)->assertDontSee('Menunggu balasan Irban')->assertSee('Tutup permintaan penjelasan dari Ibu Inspektur')
            ->call('tutup', $root->id)
            ->assertDispatched('toast', type: 'success', message: 'Permintaan penjelasan ditutup')
            ->assertDispatched('fokus-ke', id: "label-permintaan-{$root->id}")
            ->assertSee('Permintaan Penjelasan · Selesai')->assertSee('Ditutup oleh Ibu Inspektur · 25 Maret 2026 10.00 WIB')
            ->assertDontSee('Tutup permintaan');
        $this->assertSame(StatusPermintaan::Selesai, $root->fresh()->status_permintaan);

        $this->lw($this->irban)->call('tutup', $root->id)->assertForbidden();
    }

    public function test_text_is_escaped_and_empty_state(): void
    {
        $this->lw($this->pimpinan)->assertSee('Belum ada komentar.')->assertDontSee('0 komentar');

        $this->root(['isi' => "<script>alert(1)</script>\n**tebal**"]);
        $this->lw($this->admin)->assertDontSee('<script>alert(1)', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertSee('**tebal**')->assertDontSee('<strong>tebal', false)->assertSee('1 komentar')->assertDontSee('Belum ada komentar.');
    }
}
