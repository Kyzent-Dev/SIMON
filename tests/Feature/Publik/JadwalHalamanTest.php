<?php

namespace Tests\Feature\Publik;

use App\Livewire\Publik\Jadwal;
use App\Models\IrbanWilayah;
use App\Models\Objek;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Services\Publik\JadwalPublik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Public Jadwal PKPT page (design/public/jadwal.md). Fixture: PublikFixture (today 15 Desember 2026).
 * a1: Irban A, Jenis Satu, 5 Jan–31 Mar 2026, target 2, 1 public SPT, wilayah "Kec X, Kec Y".
 * b1: Irban B, Jenis Dua, 1 Apr–30 Jun 2026, target null → 1, 1 public SPT, wilayah "Dinas D, Kec Y".
 * Grid: column = (bulan − 1) × 5 + min(5, ceil(hari / 7)); the first grid column is the jenis name, so a bar from column m
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

        // a1: 5 Jan → (0×5)+1 = 1, 31 Mar → (2×5)+5 = 15 → grid-column 2 / 17.
        $this->assertStringContainsString('aria-label="Jenis Satu, Irban A, Kec X, Kec Y, 5 Januari–31 Maret 2026"', $html);
        $this->assertMatchesRegularExpression('/data-bar[^>]*grid-column: 2 \/ 17/', $html);
        // b1: 1 Apr → 15+1 = 16, 30 Jun → 25+5 = 30 → grid-column 17 / 32.
        $this->assertStringContainsString('aria-label="Jenis Dua, Irban B, Dinas D, Kec Y, 1 April–30 Juni 2026"', $html);
        $this->assertMatchesRegularExpression('/data-bar[^>]*grid-column: 17 \/ 32/', $html);
        // Today 15 Des 2026 → (11×5)+3 = 58.
        $this->assertStringContainsString('Hari ini', $html);
        // Progress text per item (detail): a1 1 of 2, b1 1 of 1.
        $this->assertStringContainsString('1 dari 2 SPT selesai', $html);
        $this->assertStringContainsString('1 dari 1 SPT selesai', $html);
        $this->assertStringContainsString('5 Januari 2026 – 31 Maret 2026', $html);
    }

    public function test_revised_item_shows_original_outline_and_text(): void
    {
        $item = PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->c->id, 'jenis_penugasan_id' => $this->j1->id,
            'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-31', 'target_spt' => 4]);
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $item->id, 'tanggal_mulai' => '2026-08-03', 'tanggal_selesai' => '2026-08-28']);

        $html = $this->get('/jadwal')->getContent();
        // Semula 1 Jul → 30+1 = 31, 31 Jul → 30+5 = 35 → 32 / 37 (dashed outline). Bar 3 Aug → 36, 28 Aug → 35+4 = 39 → 37 / 41.
        $this->assertMatchesRegularExpression('/data-semula[^>]*grid-column: 32 \/ 37/', $html);
        $this->assertMatchesRegularExpression('/data-bar[^>]*grid-column: 37 \/ 41/', $html);
        $this->assertStringContainsString('Jadwal direvisi: semula 1–31 Juli 2026', $html);
        $this->assertStringContainsString('aria-label="Jenis Satu, Irban C, Belum ditentukan, 3 Agustus–28 Agustus 2026"', $html);
        $this->assertStringContainsString('0 dari 4 SPT selesai', $html);
        $this->assertSame(1, substr_count($html, 'Jadwal direvisi'));
    }

    public function test_monthly_view_months_weeks_and_counts(): void
    {
        PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->b->id, 'jenis_penugasan_id' => $this->j2->id,
            'tanggal_mulai' => '2026-03-20', 'tanggal_selesai' => '2026-03-25']);

        // Jan: a1 (Minggu 1, 5 Jan). Feb: a1 (later month → Minggu 1). Mar: a1 (Minggu 1) + new (20 Mar → Minggu 3) = 2.
        // Apr–Jun: b1 = 1 each. Jul–Des: 0.
        $this->get('/jadwal')->assertSeeInOrder([
            'Januari 2026 · 1 penugasan', 'Minggu 1', 'Jenis Satu', 'Irban A · Kec X, Kec Y', '5 Januari – 31 Maret 2026',
            'Februari 2026 · 1 penugasan', 'Minggu 1',
            'Maret 2026 · 2 penugasan', 'Minggu 1', 'Jenis Satu', 'Minggu 3', 'Jenis Dua', 'Irban B · Belum ditentukan', '20–25 Maret 2026',
            'April 2026 · 1 penugasan', 'Mei 2026 · 1 penugasan', 'Juni 2026 · 1 penugasan', 'Juli 2026 · 0 penugasan', 'Desember 2026 · 0 penugasan',
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
        foreach ([$this->kecX, $this->kecY, $this->dinasD, $bumd] as $o) {
            IrbanWilayah::create(['irban_id' => $this->a->id, 'tahun' => 2026, 'objek_id' => $o->id]);
        }

        $this->get('/jadwal')->assertSeeInOrder(['Wilayah Pengawasan', 'Irban A', 'Irban B', 'Irban C',
            'Kecamatan', '(2)', 'Kec X', 'Kec Y', 'Dinas/Badan', '(1)', 'Dinas D', 'BUMD', '(1)', 'BUMD M'])
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
