<?php

namespace Tests\Feature\Publik;

use App\Livewire\Publik\Jadwal;
use App\Models\IrbanWilayah;
use App\Models\Objek;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Services\Publik\JadwalPublik;
use App\Support\CachePublik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\JadwalItem;
use Tests\TestCase;

/**
 * Public Jadwal PKPT page (design/public/jadwal.md). Fixture: PublikFixture (today 15 Desember 2026).
 * a1: Irban A, Jenis Satu, 5 Jan–31 Mar 2026, target 2, 1 public SPT, wilayah "Kec X, Kec Y".
 * b1: Irban B, Jenis Dua, 1 Apr–30 Jun 2026, target null → 1, 1 public SPT, wilayah "Dinas D, Kec Y".
 * Grid: column = (bulan − 1) × 5 + minggu from MingguPkpt (a week belongs to its Wednesday's month); the first grid column is
 * the jenis name, so a bar from column m
 * to s is placed at `grid-column: m+1 / s+2`.
 */
class JadwalHalamanTest extends TestCase
{
    use PublikFixture;
    use RefreshDatabase;

    public function test_guest_sees_the_disahkan_schedule(): void
    {
        $this->get('/jadwal')->assertOk()
            ->assertSee('<h1', false)->assertSee('Jadwal PKPT 2026')
            ->assertSee('Program Kerja Pengawasan Tahunan yang telah disahkan')
            ->assertSee('Linimasa')->assertSee('Bulanan')->assertSee('Semua Irban')
            ->assertSee('data-motion="bars"', false);
        $this->assertSame(url('/jadwal'), route('publik.jadwal'));
    }

    public function test_bars_have_exact_labels_and_grid_columns(): void
    {
        $html = $this->get('/jadwal')->getContent();

        // a1: 5 Jan → Jan M1 = 1, 31 Mar (Tuesday; Wednesday 1 Apr) → Apr M1 = 16 → grid-column 2 / 18.
        $this->assertStringContainsString('aria-label="Jenis Satu, Irban A, Kec X, Kec Y, 5 Januari–31 Maret 2026"', $html);
        $this->assertMatchesRegularExpression('/data-bar[^>]*grid-column: 2 \/ 18/', $html);
        // b1: 1 Apr → Apr M1 = 16, 30 Jun (Tuesday; Wednesday 1 Jul) → Jul M1 = 31 → grid-column 17 / 33.
        $this->assertStringContainsString('aria-label="Jenis Dua, Irban B, Dinas D, Kec Y, 1 April–30 Juni 2026"', $html);
        $this->assertMatchesRegularExpression('/data-bar[^>]*grid-column: 17 \/ 33/', $html);
        // Today 15 Des 2026 → Des M3 = 58.
        // 2026 has 8 four-week months: their column 5 is hatched (header + body) = 16 cells.
        $this->assertSame(16, substr_count($html, 'data-minggu-kosong'));
        $this->assertStringContainsString('Hari ini', $html);
        // Progress text per item (detail): a1 1 of 2, b1 1 of 1.
        $this->assertStringContainsString('1 dari 2 SPT selesai', $html);
        $this->assertStringContainsString('1 dari 1 SPT selesai', $html);
        $this->assertStringContainsString('5 Januari 2026 – 31 Maret 2026', $html);
    }

    public function test_hari_ini_marker_and_month_tint_sit_on_the_current_week(): void
    {
        $html = $this->get('/jadwal')->getContent();

        // 15 Des 2026 → column 58: the chip is centred on it, the 1px line sits in its cell, and the five December cells (56–60) are tinted.
        $this->assertStringContainsString('style="left: calc((58 - 0.5) / 60 * 100%)">Hari ini', $html);
        $this->assertSame(5, preg_match_all('/<span\s+class="[^"]*border-l[^"]*bg-mint-50">/', $html));
        $this->assertSame(1, substr_count($html, 'inset-y-0 left-1/2 w-0.5'));
        // Control: in another year (a second Disahkan PKPT) there is no marker at all.
        Pkpt::factory()->disahkan()->create(['tahun' => 2025]);
        $this->get('/jadwal?tahun=2025')->assertSee('Jadwal PKPT 2025')->assertDontSee('Hari ini');
    }

    public function test_bar_label_is_measured_and_short_bars_keep_the_text_in_a_title(): void
    {
        // 3 weeks (Aug M1–M3): 3 columns, far below 8; a1 spans 16 columns.
        PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->a->id, 'jenis_penugasan_id' => $this->j2->id,
            'tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-18']);
        $html = $this->get('/jadwal')->getContent();

        // The label is a span shown by the measured column count (kolMin), not a fixed server rule.
        $this->assertStringContainsString('kolMin: 8', $html);
        $this->assertMatchesRegularExpression('/x-bind:title="kolMin <= 16 \? null : /', $html);
        $this->assertMatchesRegularExpression('/<span x-show="kolMin <= 16"\s*>Irban A · Kec X, Kec Y<\/span>/', $html);
        // Short bar: title for sighted pointer users from the first paint, the label span stays hidden (x-cloak) until measured.
        $this->assertMatchesRegularExpression('/<button[^>]*data-bar[^>]*title="Irban A · [^"]*"[^>]*x-bind:title="kolMin <= 3 /', $html);
        $this->assertMatchesRegularExpression('/<span x-show="kolMin <= 3"\s+x-cloak\s*>/', $html);
        // Control: the long bar has no static title and its span is not cloaked.
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*aria-label="Jenis Satu, Irban A, Kec X, Kec Y, 5 Januari[^>]*\stitle="/', $html);
    }

    public function test_legend_chips_are_toggle_buttons_and_filter_drawer_opens_on_tahun(): void
    {
        $html = $this->get('/jadwal')->getContent();

        $this->assertSame(2, preg_match_all('/<button type="button" data-jenis="\d+" aria-pressed="false" x-bind:aria-pressed="\(sorot === \d+\)\.toString\(\)"/', $html));
        // Mobile Filter button opens the named drawer; its Tahun select takes initial focus (no timer needed).
        $this->assertStringContainsString("\$dispatch('open-modal', { name: 'filter-jadwal' })", $html);
        $this->assertMatchesRegularExpression('/<select[^>]*id="tahun-m"[^>]*autofocus/', $html);
        $this->assertStringNotContainsString('setTimeout', $html);
    }

    public function test_toggle_has_icons_and_detail_is_modal_only_as_a_bottom_sheet(): void
    {
        $html = $this->get('/jadwal')->getContent();

        $this->assertSame(2, preg_match_all('/<label[^>]*>\s*<input type="radio" name="tampilan"[^>]*>\s*(?:<!--.*?-->)?<svg[^>]*aria-hidden="true"/s', $html));
        // Not a hard-coded aria-modal="true" on the detail: the desktop popover is non-modal, the sheet (<768) turns it on.
        $this->assertStringContainsString('aria-modal="false" x-bind:aria-modal="modal.toString()"', $html);
        $this->assertStringContainsString('x-trap.noreturn="aktif !== null" role="dialog" aria-modal="false"', $html);
        // The two views stay hidden until Alpine picks one (no flash of both), and are visible again without JS.
        $this->assertSame(2, substr_count($html, 'x-cloak data-tampilan'));
        $this->assertStringContainsString('[data-tampilan][x-cloak]{display:block!important}', $html);
    }

    public function test_monthly_week_headings_carry_their_date_range_and_hatch_is_a_utility(): void
    {
        $html = $this->get('/jadwal')->getContent();

        // Januari 2026: Minggu 1 is Mon 5 – Fri 9 (MingguPkpt); the range sits next to the heading, not instead of it.
        $this->assertMatchesRegularExpression('/Minggu 1(?:<!--.*?-->)?\s*<span[^>]*>· 5–9 Januari 2026<\/span>/su', $html);
        // The week-5 hatch is the shared utility, not an arbitrary gradient class.
        $this->assertSame(16, substr_count($html, 'arsir-minggu'));
        $this->assertStringNotContainsString('repeating-linear-gradient', $html);
    }

    public function test_revised_item_shows_original_outline_and_text(): void
    {
        $item = PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->c->id, 'jenis_penugasan_id' => $this->j1->id,
            'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-31', 'target_spt' => 4]);
        JadwalItem::geser($item, '2026-08-03', '2026-08-28');

        // Columns from the page's own data, not its markup (Fase 11.2 Task 6 butir 2): semula 1 Jul → Jul M1 = 31, 31 Jul → Jul M5 = 35;
        // bar 3 Aug → Agu M1 = 36, 28 Aug → Agu M4 = 39.
        $bar = collect(Livewire::test(Jadwal::class)->viewData('linimasa')['baris'])->flatMap(fn ($b) => collect($b['lajur'])->flatten(1))
            ->firstWhere('item.id', $item->id);
        $this->assertSame([36, 39, [31, 35]], [$bar['mulai'], $bar['selesai'], $bar['semula']]);

        $html = $this->get('/jadwal')->getContent();
        $this->assertStringContainsString('Jadwal direvisi: semula 1–31 Juli 2026', $html);
        $this->assertStringContainsString('aria-label="Jenis Satu, Irban C, Belum ditentukan, 3 Agustus–28 Agustus 2026"', $html);
        $this->assertStringContainsString('0 dari 4 SPT selesai', $html);
        $this->assertSame(1, substr_count($html, 'Jadwal direvisi'));
    }

    public function test_monthly_view_months_weeks_and_counts(): void
    {
        PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->b->id, 'jenis_penugasan_id' => $this->j2->id,
            'tanggal_mulai' => '2026-03-20', 'tanggal_selesai' => '2026-03-25']);

        // Items appear under every week they overlap (MingguPkpt). Jan/Feb: a1 in M1–M4. Mar: a1 M1–M4 + new (20–25 Mar → M3–M4) = 2.
        // Apr: a1 (31 Mar sits in Apr M1) + b1 = 2. Mei, Jun: b1. Jul: b1 (30 Jun sits in Jul M1). Agu–Des: 0.
        $this->get('/jadwal')->assertSeeInOrder([
            'Januari 2026 · 1 penugasan', 'Minggu 1', 'Jenis Satu', 'Irban A · Kec X, Kec Y', '5 Januari – 31 Maret 2026',
            'Februari 2026 · 1 penugasan', 'Minggu 1',
            'Maret 2026 · 2 penugasan', 'Minggu 1', 'Jenis Satu', 'Minggu 3', 'Jenis Dua', 'Irban B · Belum ditentukan', '20–25 Maret 2026',
            'April 2026 · 2 penugasan', 'Mei 2026 · 1 penugasan', 'Juni 2026 · 1 penugasan', 'Juli 2026 · 1 penugasan',
            'Agustus 2026 · 0 penugasan', 'Desember 2026 · 0 penugasan',
        ]);
    }

    public function test_table_twin_rows(): void
    {
        $this->get('/jadwal')->assertSee('Lihat sebagai tabel')->assertSeeInOrder([
            '<table', 'Jenis Penugasan', 'Irban', 'Wilayah', 'Mulai', 'Selesai', 'Progres',
            'Jenis Dua', 'Irban B', 'Dinas D, Kec Y', '1 April 2026', '30 Juni 2026', '1 dari 1 SPT selesai',
            'Jenis Satu', 'Irban A', 'Kec X, Kec Y', '5 Januari 2026', '31 Maret 2026', '1 dari 2 SPT selesai', '</table>',
        ], false);
    }

    public function test_url_filters_and_invalid_values_fall_back(): void
    {
        $lama = Pkpt::factory()->disahkan()->create(['tahun' => 2025]);
        PkptItem::factory()->create(['pkpt_id' => $lama->id, 'irban_id' => $this->c->id, 'jenis_penugasan_id' => $this->j2->id,
            'tanggal_mulai' => '2025-02-02', 'tanggal_selesai' => '2025-02-10']);

        $this->get('/jadwal?tahun=2025')->assertSee('Jadwal PKPT 2025')->assertSee('Irban C, Belum ditentukan, 2 Februari–10 Februari 2025')
            ->assertDontSee('Irban A, Kec X')->assertDontSee('Hari ini'); // not the current year
        $this->get('/jadwal?tahun=1999')->assertSee('Jadwal PKPT 2026');
        $this->get('/jadwal?tahun=abc')->assertSee('Jadwal PKPT 2026');

        $this->get('/jadwal?irban='.$this->a->id)->assertSee('Jenis Satu, Irban A,')->assertDontSee('Jenis Dua, Irban B,');
        $this->get('/jadwal?irban=99999')->assertSee('Jenis Satu, Irban A,')->assertSee('Jenis Dua, Irban B,');

        Livewire::test(Jadwal::class)->set('tahun', '1999')->assertSet('tahun', '2026')
            ->set('irban', '99999')->assertSet('irban', '')
            ->set('irban', (string) $this->b->id)->assertSee('Jenis Dua, Irban B,')->assertDontSee('Jenis Satu, Irban A,');
    }

    public function test_bar_grow_marker_only_on_first_render(): void
    {
        Livewire::test(Jadwal::class)->assertSeeHtml('data-motion="bars"')
            ->set('irban', (string) $this->a->id)->assertDontSeeHtml('data-motion="bars"')->assertSeeHtml('data-bar');
    }

    public function test_wilayah_panel_lists(): void
    {
        $bumd = Objek::factory()->create(['nama' => 'BUMD M', 'jenis' => 'bumd']);
        $rsud = Objek::factory()->create(['nama' => 'RSUD R', 'jenis' => 'rsud']);
        foreach ([$this->kecX, $this->kecY, $this->dinasD, $bumd, $rsud] as $o) {
            IrbanWilayah::create(['irban_id' => $this->a->id, 'tahun' => 2026, 'objek_id' => $o->id]);
        }

        // Fase 11: RSUD is its own list in WilayahPengawasan but the public page keeps three columns (RSUD under Dinas/Badan).
        $this->get('/jadwal')->assertSeeInOrder(['Wilayah Pengawasan', 'Irban A', 'Irban B', 'Irban C',
            'Kecamatan', '(2)', 'Kec X', 'Kec Y', 'Dinas/Badan', '(2)', 'Dinas D', 'RSUD R', 'BUMD', '(1)', 'BUMD M'])
            ->assertSee('id="panel-wilayah-tab-0" aria-selected="true"', false);

        Livewire::test(Jadwal::class)->set('wilayahTab', '1')->assertSeeHtml('id="panel-wilayah-tab-1" aria-selected="true"')
            ->assertSeeInOrder(['Kecamatan', '(0)', 'Belum ada wilayah.'])
            ->set('wilayahTab', '9')->assertSet('wilayahTab', '0');
    }

    public function test_empty_state_without_a_disahkan_pkpt(): void
    {
        Pkpt::query()->update(['status' => 'draf']);

        $this->get('/jadwal')->assertOk()->assertSee('Belum ada PKPT yang disahkan untuk tahun 2026')->assertSee('Pilih tahun lain')
            ->assertDontSee('data-bar', false);
    }

    public function test_empty_state_with_an_irban_filter(): void
    {
        // Irban C has no item in the Disahkan PKPT 2026: the irban-specific title.
        $this->get('/jadwal?irban='.$this->c->id)->assertSee('Belum ada jadwal Irban C pada tahun 2026')
            ->assertDontSee('Belum ada PKPT yang disahkan');

        // No Disahkan PKPT at all: the specified copy, even with an irban selected.
        Pkpt::query()->update(['status' => 'draf']);
        CachePublik::segarkan(); // query-builder write: no model event
        $this->get('/jadwal?irban='.$this->a->id)->assertSee('Belum ada PKPT yang disahkan untuk tahun 2026')
            ->assertDontSee('Belum ada jadwal Irban');
    }

    public function test_error_state_when_the_service_fails(): void
    {
        $this->mock(JadwalPublik::class)->shouldReceive('item')->andThrow(new \RuntimeException('db down'));

        $this->get('/jadwal')->assertOk()->assertSee('Jadwal gagal dimuat. Muat ulang halaman.')->assertSee('Coba lagi');
    }

    public function test_no_private_data_in_html(): void
    {
        $html = $this->get('/jadwal')->getContent();
        foreach (['RAHASIA', '199001012020011001', 'FACTORY', 'Desa X1', $this->v1->nomor_lengkap] as $rahasia) {
            if ($rahasia) {
                $this->assertStringNotContainsString($rahasia, $html);
            }
        }
    }
}
