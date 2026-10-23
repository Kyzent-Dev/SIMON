<?php

namespace Tests\Feature\Rekap;

use App\Enums\Role;
use App\Enums\StatusPermintaan;
use App\Livewire\Pimpinan\Dasbor;
use App\Models\Irban;
use App\Models\Komentar;
use App\Models\Pkpt;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Models\User;
use App\Services\MonitoringSpt;
use App\Services\RekapTriwulan;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dasbor Pimpinan (Fase 8, Task 5) on the RekapFixture data (today 15 Desember 2026). The factory sets spt.tahun = 2026 for every
 * accepted SPT (s11 too, although its dates are in 2025), so the year-scoped cards see s1–s6, s9–s11 minus s7 (Dibatalkan):
 *   Berjalan  = s2, s9, s10                            = 3
 *   Selesai   = s1, s3, s4, s5, s6, s11                = 6
 *   Terlambat (all years) = s2 (31 Mar), s9 (20 Agu), s10 (5 Nov) + $lama (tahun 2025, 5 Jun 2025):
 *               Penyusunan Laporan and > 14 days past the end = 4
 *   Tidak Sesuai PKPT = s11 (Des 2025 vs item b1 Mei–Jun 2026)    = 1
 *   Permintaan Penjelasan Terbuka (all years) = open on s10 + open on an SPT of tahun 2025 = 2 (a closed one and a reply do not count)
 */
class DasborPimpinanTest extends TestCase
{
    use RefreshDatabase;
    use RekapFixture { setUp as fixtureSetUp; }

    private User $pimpinan;

    protected function setUp(): void
    {
        $this->fixtureSetUp();
        $this->withoutVite();
        $this->pimpinan = User::factory()->create(['role' => Role::Pimpinan]);

        $s10 = Spt::whereDate('tanggal_mulai', '2026-11-01')->sole();
        $buka = Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $s10->id]);
        Komentar::factory()->balasanDari($buka)->create();
        Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $s10->id, 'status_permintaan' => StatusPermintaan::Selesai]);
        $lama = Spt::factory()->diterima()->create(['irban_id' => $this->a->id, 'tanggal_mulai' => '2025-06-01', 'tanggal_selesai' => '2025-06-05']);
        $lama->forceFill(['tahun' => 2025])->save();
        Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $lama->id]);
    }

    private function halaman(array $query = [])
    {
        return Livewire::withQueryParams($query)->actingAs($this->pimpinan)->test(Dasbor::class);
    }

    private function kartu($halaman): array
    {
        return collect($halaman->viewData('kartu'))->keyBy('label')->all();
    }

    public function test_route_is_the_livewire_page_and_other_roles_are_forbidden(): void
    {
        $this->actingAs($this->pimpinan)->get('/pimpinan')->assertOk()
            ->assertSee('<h1', false)->assertSee('Dasbor Pimpinan')->assertSeeLivewire(Dasbor::class);
        foreach ([Role::Irban, Role::BagianUmum, Role::Admin] as $role) {
            $u = User::factory()->create(['role' => $role, 'irban_id' => $role === Role::Irban ? $this->a->id : null]);
            $this->actingAs($u)->get('/pimpinan')->assertForbidden();
            Livewire::actingAs($u)->test(Dasbor::class)->assertForbidden();
        }
    }

    public function test_stat_cards_in_order_with_hand_computed_counts(): void
    {
        $h = $this->halaman(['tahun' => 2026]);
        $kartu = $this->kartu($h);

        $this->assertSame(['Berjalan', 'Selesai', 'Terlambat', 'Tidak Sesuai PKPT', 'Permintaan Penjelasan Terbuka', 'Perubahan PKPT Menunggu'],
            array_column($h->viewData('kartu'), 'label'));
        $this->assertSame([3, 6, 4, 1, 2, 0], array_column($h->viewData('kartu'), 'nilai'));
        $this->assertTrue($kartu['Terlambat']['alert']);
        $this->assertFalse($kartu['Berjalan']['alert']);
        $h->assertSeeHtmlInOrder(['Berjalan', 'Selesai', 'Terlambat', 'Tidak Sesuai PKPT', 'Permintaan Penjelasan Terbuka', 'Perubahan PKPT Menunggu']);
    }

    public function test_each_monitoring_card_equals_the_row_count_behind_its_link(): void
    {
        foreach ($this->kartu($this->halaman(['tahun' => 2026])) as $label => $k) {
            if (in_array($label, ['Permintaan Penjelasan Terbuka', 'Perubahan PKPT Menunggu'], true)) {
                continue;
            }
            $this->assertStringStartsWith(route('pimpinan.monitoring').'?', $k['href'], $label);
            parse_str(parse_url($k['href'], PHP_URL_QUERY), $query);
            $baris = array_sum(array_map(fn ($g) => count($g['spt']), app(MonitoringSpt::class)->baris($query)));
            $this->assertSame($baris, $k['nilai'], $label);
        }

        $kartu = $this->kartu($this->halaman(['tahun' => 2026]));
        $this->assertStringContainsString('status=berjalan', $kartu['Berjalan']['href']);
        $this->assertStringContainsString('status=selesai', $kartu['Selesai']['href']);
        $this->assertStringContainsString('penanda=terlambat', $kartu['Terlambat']['href']);
        $this->assertStringContainsString('kesesuaian=tidak_sesuai', $kartu['Tidak Sesuai PKPT']['href']);
        $this->assertStringContainsString('penjelasan=terbuka', $kartu['Permintaan Penjelasan Terbuka']['href']);
    }

    public function test_year_scoped_cards_follow_the_tahun_filter_but_terlambat_and_permintaan_count_all_years(): void
    {
        // tahun 2025: only $lama (diterima, berjalan, ended Juni 2025) is accepted with spt.tahun 2025; Terlambat stays 4 (all years).
        $kartu = $this->kartu($this->halaman(['tahun' => 2025]));
        $this->assertSame(1, $kartu['Berjalan']['nilai']);
        $this->assertSame(0, $kartu['Selesai']['nilai']);
        $this->assertSame(4, $kartu['Terlambat']['nilai']);
        $this->assertSame(2, $kartu['Permintaan Penjelasan Terbuka']['nilai']);
    }

    /** PRD §14 butir 4: an SPT of last year ending 20 Desember 2025 is Terlambat from day 15 after its end, on the default (current-year) dashboard. */
    public function test_terlambat_from_last_year_appears_on_day_15_not_14(): void
    {
        $spt = Spt::factory()->diterima()->create(['irban_id' => $this->a->id, 'tanggal_mulai' => '2025-12-01', 'tanggal_selesai' => '2025-12-20']);
        $spt->forceFill(['tahun' => 2025])->save();
        $terlambat = function (string $hari) use ($spt) {
            $this->travelTo(CarbonImmutable::parse("{$hari} 10:00", 'Asia/Jakarta'));
            $k = $this->kartu($this->halaman())['Terlambat'];
            parse_str(parse_url($k['href'], PHP_URL_QUERY), $query);
            $ids = array_merge(...array_map(fn ($g) => array_column($g['spt'], 'id'), app(MonitoringSpt::class)->baris($query)));

            return [$k['nilai'], in_array($spt->id, $ids, true), $query['tahun']];
        };

        // 3 Januari 2026 = selesai + 14: not counted. Only $lama (Juni 2025) is late; the 2026 SPT of the fixture have not ended yet.
        $this->assertSame([1, false, '2026'], $terlambat('2026-01-03'));
        // 4 Januari 2026 = selesai + 15: counted, and the monitoring rows behind the link include it.
        $this->assertSame([2, true, '2026'], $terlambat('2026-01-04'));
    }

    public function test_chart_data_and_table_twin_equal_rekap_triwulan(): void
    {
        // Hand-computed (RekapFixture): TW II Irban A 1 dari 1 (100%), Irban B 1 dari 2 (50%); Setahun A 3 dari 6 (50%), B 1 dari 3 (33%).
        $tw2 = $this->halaman(['tahun' => 2026, 'tw' => 2]);
        $this->assertSame(['periode' => 'TW II', 'label' => ['Irban A', 'Irban B'], 'rencana' => [1, 2], 'realisasi' => [1, 1], 'persen' => [100, 50]],
            $tw2->viewData('grafik'));
        $tw2->assertSeeHtmlInOrder(['Tampilkan tabel', '<table', 'Irban A', '>1<', '>1<', '100%', 'Irban B', '>2<', '>1<', '50%', '</table>']);

        $setahun = $this->halaman(['tahun' => 2026])->viewData('grafik');
        $rekap = app(RekapTriwulan::class)->hitung(2026)['irban']['baris'];
        $this->assertSame('Setahun', $setahun['periode']);
        $this->assertSame(array_column(array_column($rekap, 'total'), 'rencana'), $setahun['rencana']);
        $this->assertSame(array_column(array_column($rekap, 'total'), 'realisasi'), $setahun['realisasi']);
        $this->assertSame([6, 3], $setahun['rencana']);
        $this->assertSame([3, 1], $setahun['realisasi']);

        // TW III: Irban A has no rencana → persen null, shown as "–".
        $tw3 = $this->halaman(['tahun' => 2026, 'tw' => 3]);
        $this->assertSame([null, 0], $tw3->viewData('grafik')['persen']);
        $tw3->assertSeeHtml('role="img"')->assertSee('Realisasi vs Rencana per Irban');
    }

    public function test_changing_the_tw_dispatches_the_new_chart_data(): void
    {
        $this->halaman(['tahun' => 2026])->set('tw', '2')
            ->assertDispatched('grafik-realisasi', data: ['periode' => 'TW II', 'label' => ['Irban A', 'Irban B'], 'rencana' => [1, 2], 'realisasi' => [1, 1], 'persen' => [100, 50]]);
    }

    public function test_invalid_url_values_fall_back(): void
    {
        $h = $this->halaman(['tahun' => 'abc', 'tw' => '9']);
        $this->assertSame('2026', $h->get('tahun'));
        $this->assertSame('', $h->get('tw'));
    }

    /** Fase 9.2: the Perubahan PKPT card (alert when > 0, links to the list) and panel (max 5, "Lihat semua") replace the revisi ones. */
    public function test_perubahan_pkpt_card_and_panel(): void
    {
        $this->halaman(['tahun' => 2026])->assertSee('Perubahan PKPT Menunggu')->assertDontSee('Revisi Jadwal')
            ->assertDontSee('Menunggu Persetujuan Anda')->assertDontSee('wire:click', false)
            ->assertSeeHtml('href="'.route('pimpinan.perubahan-pkpt').'"');

        $paket = PkptPerubahan::factory()->diajukan()->count(6)->create(['pkpt_id' => Pkpt::where('tahun', 2026)->value('id')]);
        $h = $this->halaman(['tahun' => 2026])->assertSee('Menunggu Persetujuan Anda')->assertSee('Lihat semua (6)')
            ->assertSeeHtml('href="'.route('pimpinan.perubahan-pkpt.show', $paket[0]).'"')
            ->assertDontSeeHtml('href="'.route('pimpinan.perubahan-pkpt.show', $paket[5]).'"')->assertDontSee('Setujui')->assertDontSee('Tolak');
        $this->assertStringContainsString('border-l-danger-700', $h->html());
    }

    public function test_link_cards(): void
    {
        $this->halaman()->assertSeeHtml('href="'.route('pimpinan.monitoring').'"')->assertSeeHtml('href="'.route('pimpinan.rekap').'"')
            ->assertSee('Monitoring SPT')->assertSee('Rekap Triwulanan');
    }

    public function test_monitoring_jumlah_matches_baris(): void
    {
        $m = app(MonitoringSpt::class);
        foreach ([['tahun' => 2026], ['tahun' => 2026, 'penanda' => 'terlambat'], ['tahun' => 2026, 'irban' => Irban::first()->id]] as $f) {
            $this->assertSame(array_sum(array_map(fn ($g) => count($g['spt']), $m->baris($f))), $m->jumlah($f));
        }
    }
}
