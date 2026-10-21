<?php

namespace Tests\Feature\Rekap;

use App\Enums\Role;
use App\Exports\ArrayExport;
use App\Jobs\BuatEkspor;
use App\Livewire\Rekap;
use App\Models\Ekspor as EksporModel;
use App\Models\Irban;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\EksporGagal;
use App\Services\Ekspor;
use App\Services\Ekspor\PembangunRekap;
use App\Services\RealisasiItemPkpt;
use App\Services\RekapTriwulan;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** Tab "Detail Realisasi" on the Rekap page (spec A.2/A.4, A.6 #6 page part). Data rules are covered by RealisasiItemPkptTest. */
class DetailRealisasiTest extends TestCase
{
    use RefreshDatabase;

    private Irban $a;

    private Irban $b;

    private Irban $c;

    private Spt $spt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00', 'Asia/Jakarta'));
        app(Settings::class)->set('rekap.dasar_triwulan', 'tanggal_selesai');
        $this->a = Irban::factory()->create(['nama' => 'Irban A']);
        $this->b = Irban::factory()->create(['nama' => 'Irban B']);
        $this->c = Irban::factory()->create(['nama' => 'Irban C']);
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $item = fn (Irban $i, string $nama, string $m, string $s) => PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $i->id,
            'jenis_penugasan_id' => JenisPenugasan::factory()->create(['nama' => $nama])->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s]);
        $bos = $item($this->a, 'Pengawasan Dana BOS', '2026-02-02', '2026-02-27');
        $item($this->a, 'Reviu LKjIP', '2026-03-02', '2026-03-06');
        $item($this->b, 'Audit Ketaatan OPD', '2026-02-09', '2026-02-13');
        $this->spt = Spt::factory()->diterima()->create(['irban_id' => $this->a->id, 'pkpt_item_id' => $bos->id, 'jenis_penugasan_id' => $bos->jenis_penugasan_id,
            'tanggal_mulai' => '2026-02-02', 'tanggal_selesai' => '2026-02-06']);
    }

    private function detail(User $u, array $props = [])
    {
        $t = Livewire::actingAs($u)->test(Rekap::class);
        foreach (['tab' => 'detail', ...$props] as $k => $v) {
            $t->set($k, $v);
        }

        return $t;
    }

    private function irbanA(): User
    {
        return User::factory()->create(['role' => Role::Irban, 'irban_id' => $this->a->id]);
    }

    public function test_pimpinan_has_three_tabs_and_irban_two(): void
    {
        $this->actingAs(User::factory()->create(['role' => Role::Pimpinan]))->get('/pimpinan/rekap')->assertOk()
            ->assertSeeInOrder(['role="tab"', 'Per Irban', 'Gabungan', 'Detail Realisasi'], false);
        $html = $this->actingAs($this->irbanA())->get('/irban/rekap')->assertOk()
            ->assertSeeInOrder(['role="tablist"', 'Ringkasan', 'Detail Realisasi'], false)->getContent();
        $this->assertStringNotContainsString('Gabungan', $html);
        $this->assertSame(2, substr_count($html, 'id="rekap-panel-tab-'));
    }

    public function test_detail_tab_lists_items_per_irban_with_spt_rows_and_empty_rows(): void
    {
        $t = $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['tw' => '1']);

        $t->assertSet('tab', 'detail')->assertSee('Kesesuaian dengan Jadwal PKPT')->assertSee('Permasalahan/Kendala')
            ->assertDontSee('Rencana vs Realisasi')->assertDontSee('Per Klasifikasi');
        $html = $t->html();
        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Irban A\s*<\/h2>/', $html);
        $this->assertStringContainsString('href="'.route('spt.show', $this->spt).'"', $html);
        // Desktop table (rendered first): BOS row with its SPT, LKjIP without SPT, B's item, C's empty state.
        $pos = 0;
        foreach (['Irban A', 'Pengawasan Dana BOS', '2–27 Februari 2026', '2–6 Februari 2026', e($this->spt->nomor_lengkap), 'Reviu LKjIP', 'Belum ada SPT',
            'Irban B', 'Audit Ketaatan OPD', 'Belum ada SPT', 'Irban C', 'Tidak ada item PKPT pada periode ini.'] as $teks) {
            $pos = strpos($html, $teks, $pos);
            $this->assertNotFalse($pos, $teks);
        }
    }

    public function test_mobile_card_has_selengkapnya_toggle_for_long_kendala(): void
    {
        $this->spt->kendala()->create(['kategori' => 'Cuaca', 'dibuat_oleh' => User::factory()->create()->id, 'uraian' => str_repeat('Banjir menghambat perjalanan tim. ', 4)]);
        $html = $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['tw' => '1'])->html();

        $this->assertStringContainsString('id="kendala-kartu-'.$this->spt->id.'"', $html);
        $this->assertStringContainsString('aria-controls="kendala-kartu-'.$this->spt->id.'"', $html);
    }

    public function test_pimpinan_irban_param_scopes_to_one_irban(): void
    {
        $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['irban' => (string) $this->b->id])
            ->assertSee('Audit Ketaatan OPD')->assertDontSee('Irban A')->assertDontSee('Pengawasan Dana BOS')->assertDontSee('Irban C');
    }

    public function test_irban_sees_only_own_irban_even_with_tampered_param(): void
    {
        $this->detail($this->irbanA(), ['irban' => (string) $this->b->id])
            ->assertSee('Pengawasan Dana BOS')->assertSee('Rekap Saya')
            ->assertDontSee('Irban B')->assertDontSee('Audit Ketaatan OPD')->assertDontSee('Irban C');
    }

    public function test_empty_state_for_irban_without_items_in_the_period(): void
    {
        $this->detail(User::factory()->create(['role' => Role::Pimpinan]), ['irban' => (string) $this->a->id, 'tw' => '3'])
            ->assertSee('Irban A')->assertSee('Tidak ada item PKPT pada periode ini.')->assertDontSee('Pengawasan Dana BOS');
    }

    private function xlsx(User $u, array $filter = []): array
    {
        $r = app(Ekspor::class)->minta($u, 'rekap', 'xlsx', ['tahun' => 2026, ...$filter]);

        return IOFactory::load($r->getFile()->getPathname())->getActiveSheet()->toArray(null, true, true, false);
    }

    private function sptKedua(): Spt
    {
        $item = $this->spt->pkptItem;

        return Spt::factory()->diterima()->create(['irban_id' => $this->a->id, 'pkpt_item_id' => $item->id, 'jenis_penugasan_id' => $item->jenis_penugasan_id,
            'tanggal_mulai' => '2026-02-16', 'tanggal_selesai' => '2026-02-20']);
    }

    public function test_xlsx_has_fourth_section_with_jenis_and_periode_only_on_first_row_of_item(): void
    {
        $dua = $this->sptKedua();
        $rows = $this->xlsx(User::factory()->create(['role' => Role::Pimpinan]), ['irban' => $this->a->id, 'tw' => 1]);
        $i = array_search('Detail Realisasi per Item PKPT', array_column($rows, 0), true);
        $this->assertNotFalse($i);
        $this->assertSame('Irban A', $rows[$i + 1][0]);
        $this->assertSame(['Jenis Penugasan', 'Periode Penugasan (PKPT)', 'Nomor SPT', 'Realisasi Periode', 'Kesesuaian dengan Jadwal PKPT', 'Status', 'Permasalahan/Kendala'], $rows[$i + 2]);
        $this->assertSame(['Pengawasan Dana BOS', '2–27 Februari 2026', $this->spt->nomor_lengkap, '2–6 Februari 2026'], array_slice($rows[$i + 3], 0, 4));
        $this->assertSame([null, null, $dua->nomor_lengkap, '16–20 Februari 2026'], array_slice($rows[$i + 4], 0, 4));
        $this->assertSame('Sesuai', $rows[$i + 3][4]);
        $this->assertNotSame('', $rows[$i + 3][5]);
    }

    public function test_xlsx_item_without_spt_and_empty_irban_rows(): void
    {
        $rows = $this->xlsx(User::factory()->create(['role' => Role::Pimpinan]), ['tw' => 1]);
        $this->assertContains(['Reviu LKjIP', '2–6 Maret 2026', '–', 'Belum ada SPT', '–', '–', '–'], $rows);
        $this->assertContains(['Tidak ada item PKPT pada periode ini.', null, null, null, null, null, null], $rows);
    }

    public function test_irban_export_ignores_tampered_irban(): void
    {
        $text = json_encode($this->xlsx($this->irbanA(), ['irban' => $this->b->id]));
        $this->assertStringContainsString('Pengawasan Dana BOS', $text);
        $this->assertStringNotContainsString('Irban B', $text);
        $this->assertStringNotContainsString('Irban C', $text);
    }

    public function test_pdf_html_has_same_section_values_as_screen(): void
    {
        $u = User::factory()->create(['role' => Role::Pimpinan]);
        $p = app(PembangunRekap::class);
        $html = $p->html($u, $p->filter($u, ['tahun' => 2026, 'tw' => 1]));
        foreach (['Detail Realisasi per Item PKPT', 'Irban A', 'Pengawasan Dana BOS', '2–27 Februari 2026', e($this->spt->nomor_lengkap), '2–6 Februari 2026',
            'Reviu LKjIP', 'Belum ada SPT', 'Irban C', 'Tidak ada item PKPT pada periode ini.'] as $teks) {
            $this->assertStringContainsString($teks, $html, $teks);
        }
    }

    public function test_jumlah_counts_detail_rows(): void
    {
        $u = User::factory()->create(['role' => Role::Pimpinan]);
        $p = app(PembangunRekap::class);
        $this->sptKedua();
        // A: BOS 2 SPT + LKjIP 1 row = 3, B: 1, C: empty row 1.
        $this->assertSame(5, $p->jumlah($u, $p->filter($u, ['tahun' => 2026])));
    }

    public function test_queued_rekap_failure_for_irban_links_to_irban_rekap(): void
    {
        $u = $this->irbanA();
        $this->assertSame('/irban/rekap', app(PembangunRekap::class)->halaman($u));
        $this->assertSame('/pimpinan/rekap', app(PembangunRekap::class)->halaman(User::factory()->create(['role' => Role::Pimpinan])));

        Notification::fake();
        $e = EksporModel::create(['user_id' => $u->id, 'jenis' => 'rekap', 'format' => 'xlsx', 'filter' => ['tahun' => 2026], 'nama_file' => 'rekap.xlsx', 'status' => 'menunggu']);
        $this->app->bind(PembangunRekap::class, fn () => new class(app(RekapTriwulan::class), app(RealisasiItemPkpt::class)) extends PembangunRekap
        {
            public function export(User $u, array $f): ArrayExport
            {
                throw new \RuntimeException('boom');
            }
        });
        app()->call([new BuatEkspor($e), 'handle']);
        Notification::assertSentTo($u, EksporGagal::class, fn (EksporGagal $n) => $n->url() === '/irban/rekap');
    }
}
