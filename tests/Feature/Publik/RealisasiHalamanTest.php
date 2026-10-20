<?php

namespace Tests\Feature\Publik;

use App\Livewire\Publik\Realisasi;
use App\Models\JenisPenugasan;
use App\Services\Publik\RealisasiPublik;
use App\Services\RekapTriwulan;
use App\Services\Settings;
use App\Services\Triwulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

/**
 * Public Realisasi page (design/public/realisasi.md). Fixture: PublikFixture (today 15 Desember 2026, basis tanggal_selesai).
 * Public SPT: v1 Irban A, Jenis Satu, 3–20 Feb 2026 (TW I, Kec X); v2 Irban B, Jenis Dua, 28 Mei – 2 Jun 2026 (TW II, Dinas D, Kec Y).
 * Items 2026: a1 (A, target 2, TW I), b1 (B, target null → 1, TW II). Irban A, B, C by name.
 */
class RealisasiHalamanTest extends TestCase
{
    use PublikFixture;
    use RefreshDatabase;

    private const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov', 'Des'];

    private function nol(array $isi = []): array
    {
        return array_replace(array_fill(0, 12, 0), $isi);
    }

    private function js(mixed $data): string
    {
        return Js::from($data)->toHtml();
    }

    public function test_guest_sees_the_page(): void
    {
        $this->get('/realisasi')->assertOk()
            ->assertSee('Realisasi Pengawasan 2026')
            ->assertSee('SPT Selesai per Bulan')->assertSee('Proporsi')->assertSee('Realisasi vs Rencana PKPT per Irban')
            ->assertSee('Semua Triwulan')->assertSee('Semua Bulan')->assertSee('Semua Irban')->assertSee('Semua Wilayah')->assertSee('Semua Jenis')
            ->assertSee('data-motion="fade-up"', false);
        $this->assertSame(url('/realisasi'), route('publik.realisasi'));
        $this->assertSame(1, substr_count($this->get('/realisasi')->getContent(), '<h1'));
    }

    public function test_no_private_value_in_the_html(): void
    {
        $html = $this->get('/realisasi')->getContent();
        foreach (['RAHASIA', $this->v1->nomor_lengkap, '199001012020011001', 'Desa X1', 'Nomor SPT'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $html);
        }
    }

    public function test_url_filters_are_clamped(): void
    {
        // 2027 is Draf, unknown values fall back, Dinas D is not a kecamatan.
        Livewire::withQueryParams(['tahun' => '2027', 'tw' => '9', 'bulan' => '13', 'irban' => '999', 'wilayah' => (string) $this->dinasD->id, 'jenis' => 'x', 'urut' => 'acak'])
            ->test(Realisasi::class)
            ->assertSet('tahun', '2026')->assertSet('tw', '')->assertSet('bulan', '')->assertSet('irban', '')
            ->assertSet('wilayah', '')->assertSet('jenis', '')->assertSet('urut', 'desc');

        Livewire::withQueryParams(['tw' => '1', 'bulan' => '2', 'irban' => (string) $this->a->id, 'wilayah' => (string) $this->kecX->id, 'jenis' => (string) $this->j1->id, 'urut' => 'asc'])
            ->test(Realisasi::class)
            ->assertSet('tw', '1')->assertSet('bulan', '2')->assertSet('irban', (string) $this->a->id)
            ->assertSet('wilayah', (string) $this->kecX->id)->assertSet('jenis', (string) $this->j1->id)->assertSet('urut', 'asc')
            ->assertSee('Hapus filter TW I')->assertSee('Hapus filter Februari')->assertSee('Hapus filter Irban A')
            ->assertSee('Hapus filter Kec X')->assertSee('Hapus filter Jenis Satu')->assertSee('Atur ulang');

        // Bulan outside the chosen TW is dropped: Februari is TW I, not TW II.
        Livewire::withQueryParams(['tw' => '2', 'bulan' => '2'])->test(Realisasi::class)->assertSet('tw', '2')->assertSet('bulan', '');
        // Bulan options follow the TW: TW II offers April–Juni only.
        Livewire::withQueryParams(['tw' => '2'])->test(Realisasi::class)->assertSeeHtml('<option value="5"')->assertDontSeeHtml('<option value="7"');
    }

    public function test_removing_chips_and_reset(): void
    {
        Livewire::withQueryParams(['tw' => '1', 'irban' => (string) $this->a->id])->test(Realisasi::class)
            ->call('hapus', 'irban')->assertSet('irban', '')->assertSet('tw', '1')->assertDispatched('fokus-filter')
            ->call('aturUlang')->assertSet('tw', '')->assertSet('tahun', '2026');
    }

    public function test_kpi_line(): void
    {
        // 2 selesai of rencana 2 + 1 = 3 → 66,7 %.
        $this->get('/realisasi')->assertSeeText('2 SPT selesai dari 3 SPT yang direncanakan · 66,7% realisasi');
        // Irban A: v1 of target 2 → 50,0 %.
        Livewire::withQueryParams(['irban' => (string) $this->a->id])->test(Realisasi::class)
            ->assertSeeText('1 SPT selesai dari 2 SPT yang direncanakan · 50,0% realisasi');
        // A jenis without items: nothing planned → –.
        $j3 = JenisPenugasan::factory()->create(['nama' => 'Jenis Tiga']);
        Livewire::withQueryParams(['jenis' => (string) $j3->id])->test(Realisasi::class)
            ->assertSeeText('0 SPT selesai dari 0 SPT yang direncanakan · – realisasi');
    }

    public function test_line_chart_json_and_twin(): void
    {
        // Single series: v1 Februari, v2 Juni.
        $tunggal = ['bulan' => self::BULAN, 'seri' => [['nama' => 'SPT selesai', 'data' => $this->nol([1 => 1, 5 => 1]), 'warna' => null]]];
        $this->assertSame($this->nol([1 => 1, 5 => 1]), app(RealisasiPublik::class)->perBulan(['tahun' => 2026], false));
        $this->get('/realisasi')->assertSee($this->js($tunggal), false)
            ->assertSee('Grafik garis: SPT selesai per bulan 2026, tertinggi Februari dengan 1 SPT')
            ->assertSeeInOrder(['Februari', '1', 'Maret', '0']);

        // Per Irban: one series per irban (all three), colour index by name order.
        $perIrban = ['bulan' => self::BULAN, 'seri' => [
            ['nama' => 'Irban A', 'data' => $this->nol([1 => 1]), 'warna' => 0],
            ['nama' => 'Irban B', 'data' => $this->nol([5 => 1]), 'warna' => 1],
            ['nama' => 'Irban C', 'data' => $this->nol(), 'warna' => 2],
        ]];
        Livewire::test(Realisasi::class)->set('perIrban', true)
            ->assertDispatched('grafik-publik', fn ($e, $p) => $p['garis'] === $perIrban)
            ->assertSeeInOrder(['Bulan', 'Irban A', 'Irban B', 'Irban C', 'Februari', '1', '0', '0']);
    }

    public function test_donut_chart_json_and_twin(): void
    {
        // Irban: A 1, B 1 of 2 → 50,0 % each (C has none, so no slice).
        $irban = app(RealisasiPublik::class)->proporsi(['tahun' => 2026], 'irban');
        $this->assertSame([['label' => 'Irban A', 'jumlah' => 1, 'persen' => 50.0, 'warna' => 0], ['label' => 'Irban B', 'jumlah' => 1, 'persen' => 50.0, 'warna' => 1]], $irban);
        $this->get('/realisasi')->assertSee($this->js(['irisan' => $irban]), false)
            ->assertSeeInOrder(['Irban A', '1 SPT', '50,0%', 'Irban B', '1 SPT', '50,0%']);

        // Jenis: eight jenis → six slices + Lainnya. J3 gets 2 SPT, J4–J8 one each; with v1 (J1) and v2 (J2) total = 2 + 2 + 5 = 9.
        // Sorted by count desc then name: J3 (2), then the seven 1-counts by name; the last two by name are summed into Lainnya = 2.
        foreach (['Jenis Tiga' => 2, 'Jenis Empat' => 1, 'Jenis Lima' => 1, 'Jenis Enam' => 1, 'Jenis Tujuh' => 1, 'Jenis Delapan' => 1] as $nama => $n) {
            $j = JenisPenugasan::factory()->create(['nama' => $nama]);
            for ($k = 0; $k < $n; $k++) {
                $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2026-02-03', '2026-02-20', [], $j->id);
            }
        }
        $jenis = app(RealisasiPublik::class)->proporsi(['tahun' => 2026], 'jenis');
        $this->assertCount(7, $jenis);
        $this->assertSame(['Jenis Tiga', 2, 22.2], [$jenis[0]['label'], $jenis[0]['jumlah'], $jenis[0]['persen']]);
        $this->assertSame(['Lainnya', 2, 22.2, null], array_values($jenis[6]));
        Livewire::test(Realisasi::class)->set('proporsi', 'jenis')
            ->assertDispatched('grafik-publik', fn ($e, $p) => $p['donat'] === ['irisan' => $jenis])
            ->assertSee('Lainnya')->assertSee('9 SPT');
    }

    public function test_bar_chart_follows_its_own_tw(): void
    {
        $svc = app(RealisasiPublik::class);
        // Setahun: rencana A 2, B 1, C 0; realisasi A 1, B 1, C 0.
        $setahun = $svc->rencanaVsRealisasi(['tahun' => 2026], null);
        $this->assertSame([[2, 1, 0], [1, 1, 0]], [$setahun['rencana'], $setahun['realisasi']]);
        $this->get('/realisasi')->assertSee($this->js($setahun), false)
            ->assertSeeInOrder(['Irban A', '2', '1', '50%', 'Irban B', '1', '1', '100%', 'Irban C', '0', '0', '–']);

        // Card selector: TW I…TW IV | Setahun, each with its own value (Setahun = 0, checked by default).
        $this->get('/realisasi')->assertSeeInOrder(['name="tw-grafik" value="1"', 'TW I', 'name="tw-grafik" value="4"', 'TW IV', 'name="tw-grafik" value="0" checked', 'Setahun'], false);

        // The TW filter sets the card's default TW; the card selector can switch back to Setahun.
        $tw2 = $svc->rencanaVsRealisasi(['tahun' => 2026], 2);
        $this->assertSame([[0, 1, 0], [0, 1, 0]], [$tw2['rencana'], $tw2['realisasi']]);
        $c = Livewire::withQueryParams(['tw' => '2'])->test(Realisasi::class)->assertSee($this->js($tw2), false);
        $c->set('twGrafik', '0')->assertDispatched('grafik-publik', fn ($e, $p) => $p['batang'] === $setahun);
        // Changing the TW filter makes the card follow it again.
        $c->set('tw', '1')->assertDispatched('grafik-publik', fn ($e, $p) => $p['batang'] === $svc->rencanaVsRealisasi(['tahun' => 2026], 1));
    }

    public function test_bar_card_caption_when_bulan_or_wilayah_active(): void
    {
        $this->get('/realisasi')->assertDontSee('Tidak mengikuti filter Bulan dan Wilayah.');
        Livewire::withQueryParams(['bulan' => '2'])->test(Realisasi::class)->assertSee('Tidak mengikuti filter Bulan dan Wilayah.');
        Livewire::withQueryParams(['wilayah' => (string) $this->kecY->id])->test(Realisasi::class)->assertSee('Tidak mengikuti filter Bulan dan Wilayah.');
    }

    public function test_table_columns_sort_and_cards(): void
    {
        $this->get('/realisasi')
            ->assertSeeInOrder(['Jenis Penugasan', 'Irban', 'Wilayah', 'Tanggal Pelaksanaan', 'Triwulan'])
            ->assertSee('aria-sort="descending"', false)
            // Newest first: v2 (2 Jun) before v1 (20 Feb).
            ->assertSeeInOrder(['Jenis Dua', 'Irban B', 'Dinas D, Kec Y', '28 Mei – 2 Juni 2026', 'TW II', 'Jenis Satu', 'Irban A', 'Kec X', '3–20 Februari 2026', 'TW I'])
            ->assertDontSee('Nomor SPT');

        // (Livewire's assertSeeInOrder reads the JSON-escaped update payload, so the en dash is compared in the rendered HTML.)
        $html = Livewire::test(Realisasi::class)->call('urutkan')->assertSet('urut', 'asc')->assertSeeHtml('aria-sort="ascending"')->html();
        $this->assertLessThan(strpos($html, '28 Mei – 2 Juni 2026'), strpos($html, '3–20 Februari 2026'));
    }

    public function test_mobile_cards_have_a_sort_button_bound_to_urut(): void
    {
        // Same `urutkan` action and `?urut` as the table header; the label shows the current order.
        $this->get('/realisasi')->assertSee('Urutkan: Terbaru')->assertDontSee('Urutkan: Terlama');
        Livewire::test(Realisasi::class)->assertSeeHtml('wire:click="urutkan"')
            ->call('urutkan')->assertSet('urut', 'asc')->assertSee('Urutkan: Terlama')->assertDontSee('Urutkan: Terbaru');
        Livewire::withQueryParams(['urut' => 'asc'])->test(Realisasi::class)->assertSee('Urutkan: Terlama');
    }

    public function test_charts_update_only_when_a_chart_input_changes(): void
    {
        for ($k = 0; $k < 26; $k++) {
            $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2026-03-02', '2026-03-06');
        }
        Livewire::test(Realisasi::class)->call('gotoPage', 2)->assertNotDispatched('grafik-publik');
        Livewire::test(Realisasi::class)->call('urutkan')->assertNotDispatched('grafik-publik');
        Livewire::test(Realisasi::class)->set('irban', (string) $this->a->id)->assertDispatched('grafik-publik');
        Livewire::test(Realisasi::class)->set('proporsi', 'jenis')->assertDispatched('grafik-publik');
        Livewire::withQueryParams(['irban' => (string) $this->a->id])->test(Realisasi::class)->call('hapus', 'irban')->assertDispatched('grafik-publik');
        Livewire::withQueryParams(['irban' => (string) $this->a->id])->test(Realisasi::class)->call('aturUlang')->assertDispatched('grafik-publik');
    }

    public function test_kpi_numbers_use_mint_700(): void
    {
        $html = $this->get('/realisasi')->getContent();
        $this->assertStringContainsString('font-bold text-mint-700 tabular-nums">2</span> SPT selesai', $html);
        $this->assertStringNotContainsString('text-mint-800', $html);
    }

    public function test_nomor_column_only_with_the_setting(): void
    {
        app(Settings::class)->set('publik.tampilkan_nomor_spt', true);
        $this->get('/realisasi')->assertSeeInOrder(['Nomor SPT', 'Jenis Penugasan'])->assertSee($this->v1->nomor_lengkap);
    }

    public function test_pagination_25_per_page(): void
    {
        // 26 more public SPT in March → 28 rows, 25 on page 1, 3 on page 2.
        for ($k = 0; $k < 26; $k++) {
            $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2026-03-02', '2026-03-06');
        }
        $this->get('/realisasi')->assertSee('Menampilkan 1–25 dari 28')->assertDontSee('Baris per halaman');
        Livewire::withQueryParams(['page' => '2'])->test(Realisasi::class)->assertSee('Menampilkan 26–28 dari 28');
        // A page past the end falls back to the last page.
        Livewire::withQueryParams(['page' => '9'])->test(Realisasi::class)->assertSee('Menampilkan 26–28 dari 28');
    }

    public function test_empty_state(): void
    {
        Livewire::withQueryParams(['irban' => (string) $this->c->id])->test(Realisasi::class)
            ->assertSee('Tidak ada realisasi untuk filter ini.')->assertSee('Atur ulang filter')
            ->assertSee('Grafik garis: SPT selesai per bulan 2026, belum ada SPT selesai');
    }

    public function test_a_failing_aggregate_shows_an_alert_in_its_card_only(): void
    {
        $this->app->instance(RealisasiPublik::class, new class(app(Triwulan::class), app(Settings::class), app(RekapTriwulan::class)) extends RealisasiPublik
        {
            public function proporsi(array $f, string $menurut): array
            {
                throw new RuntimeException('gagal');
            }
        });

        $html = $this->get('/realisasi')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'Grafik gagal dimuat.'));
        $this->assertStringContainsString('Grafik garis: SPT selesai per bulan 2026', $html);
        $this->assertStringContainsString('3–20 Februari 2026', $html);
        $this->assertStringNotContainsString('grafikDonat(', $html);
        $this->assertStringContainsString('grafikRealisasi(', $html);
    }

    public function test_livewire_updates_never_carry_data_motion(): void
    {
        Livewire::test(Realisasi::class)->set('irban', (string) $this->a->id)->assertDontSeeHtml('data-motion');
    }
}
