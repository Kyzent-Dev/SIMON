<?php

namespace Tests\Feature\Publik;

use App\Models\JenisPenugasan;
use App\Models\Klasifikasi;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Services\Publik\JadwalPublik;
use App\Services\Publik\RealisasiPublik;
use App\Services\RekapTriwulan;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealisasiPublikTest extends TestCase
{
    use PublikFixture;
    use RefreshDatabase;

    private const SETAHUN = ['tahun' => 2026];

    private function svc(): RealisasiPublik
    {
        return app(RealisasiPublik::class); // fresh: Triwulan reads rekap.dasar_triwulan per instance
    }

    /** @return array<int, int> counts Jan–Des of the single series */
    private function bulan(array $f): array
    {
        return $this->svc()->perBulan($f, false);
    }

    private function nol(array $isi = []): array
    {
        return array_replace(array_fill(0, 12, 0), $isi);
    }

    public function test_only_public_spt_appear_in_every_method(): void
    {
        $s = $this->svc();
        $this->assertSame(2, $s->ringkasan(self::SETAHUN)['selesai']);
        $this->assertSame($this->nol([1 => 1, 5 => 1]), $s->perBulan(self::SETAHUN, false)); // v1 Februari, v2 Juni
        $this->assertSame([['Irban A', 1], ['Irban B', 1]], array_map(fn ($p) => [$p['label'], $p['jumlah']], $s->proporsi(self::SETAHUN, 'irban')));
        $this->assertSame([1, 1, 0], $s->rencanaVsRealisasi(self::SETAHUN, null)['realisasi']);
        $this->assertSame(2, $s->tabel(self::SETAHUN, 'desc', 1)->total());
        $selesai = array_column(app(JadwalPublik::class)->item(2026, null), 'selesaiSpt', 'id');
        $this->assertEquals([$this->a1->id => 1, $this->b1->id => 1], $selesai); // key order irrelevant

        // Admin turns KHUSUS public: e1 (KHUSUS Selesai, a1, 10–12 Feb) joins.
        Klasifikasi::where('kode', 'KHUSUS')->update(['tampil_publik' => true]);
        $s = $this->svc();
        $this->assertSame(3, $s->ringkasan(self::SETAHUN)['selesai']);
        $this->assertSame($this->nol([1 => 2, 5 => 1]), $s->perBulan(self::SETAHUN, false));
        $this->assertSame(3, $s->tabel(self::SETAHUN, 'desc', 1)->total());
        $this->assertSame(2, array_column(app(JadwalPublik::class)->item(2026, null), 'selesaiSpt', 'id')[$this->a1->id]);
    }

    public function test_no_private_value_reaches_any_output(): void
    {
        $s = $this->svc();
        $j = app(JadwalPublik::class);
        $item = $j->item(2026, null);
        $semua = [
            $s->ringkasan(self::SETAHUN), $s->perBulan(self::SETAHUN, true), $s->proporsi(self::SETAHUN, 'irban'), $s->proporsi(self::SETAHUN, 'jenis'),
            $s->rencanaVsRealisasi(self::SETAHUN, null), $s->tabel(self::SETAHUN, 'desc', 1)->items(), $s->opsi(),
            $item, $j->linimasa($item, 2026), $j->bulanan($item, 2026), $j->wilayah(2026),
        ];
        $json = json_encode($semua);
        foreach (['RAHASIA', 'FACTORY/', '199001012020011001', 'Desa X1', 'nomor', 'nip', 'periode_diperiksa', 'uraian_untuk', 'keterangan', 'catatan_bagian_umum', 'nomor_lhp', 'diinput_oleh', 'pegawai', 'kendala'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $json);
        }

        $baris = $s->tabel(self::SETAHUN, 'asc', 1)->items()[0];
        $this->assertSame(['jenis', 'irban', 'wilayah', 'tanggal', 'triwulan'], array_keys($baris));
        $this->assertSame(['nama' => 'Jenis Satu', 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF'], $baris['jenis']);
        $this->assertSame(['Irban A', 'Kec X', '3–20 Februari 2026', 'TW I'], [$baris['irban'], $baris['wilayah'], $baris['tanggal'], $baris['triwulan']]);

        app(Settings::class)->set('publik.tampilkan_nomor_spt', true);
        app(Settings::class)->set('publik.tampilkan_nama_objek', true);
        $baris = $this->svc()->tabel(self::SETAHUN, 'asc', 1)->items();
        $this->assertSame(['nomor', 'jenis', 'irban', 'wilayah', 'tanggal', 'triwulan'], array_keys($baris[0]));
        $this->assertSame($this->v1->nomor_lengkap, $baris[0]['nomor']);
        $this->assertSame('Desa X1', $baris[0]['wilayah']);
        $this->assertSame('Dinas D, Kec Y', $baris[1]['wilayah']);
        // The item wilayah (Jadwal) stays at kecamatan level regardless of the setting.
        $this->assertStringNotContainsString('Desa X1', json_encode(app(JadwalPublik::class)->item(2026, null)));
    }

    public function test_tahun_tw_bulan_follow_the_basis_date_for_each_setting(): void
    {
        // f1: mulai 31 Mar, selesai 1 Apr, LHP 1 Jul. f2: mulai 31 Des 2025, selesai 1 Jan 2026, no LHP.
        $f1 = $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2026-03-31', '2026-04-01');
        $f1->forceFill(['tanggal_lhp' => '2026-07-01'])->save();
        $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2025-12-31', '2026-01-01');

        // tanggal_selesai: v1 Feb, v2 Jun, f1 Apr, f2 Jan 2026.
        $this->assertSame($this->nol([0 => 1, 1 => 1, 3 => 1, 5 => 1]), $this->bulan(self::SETAHUN));
        $this->assertSame(2, $this->svc()->ringkasan(['tahun' => 2026, 'tw' => 1])['selesai']); // f2, v1
        $this->assertSame(1, $this->svc()->ringkasan(['tahun' => 2026, 'tw' => 1, 'bulan' => 2])['selesai']); // v1
        $this->assertSame(0, $this->svc()->ringkasan(['tahun' => 2026, 'tw' => 1, 'bulan' => 4])['selesai']); // bulan outside tw
        $this->assertSame(0, $this->svc()->ringkasan(['tahun' => 2025])['selesai']);

        // tanggal_mulai: v1 Feb, v2 Mei, f1 Mar, f2 Des 2025.
        app(Settings::class)->set('rekap.dasar_triwulan', 'tanggal_mulai');
        $this->assertSame($this->nol([1 => 1, 2 => 1, 4 => 1]), $this->bulan(self::SETAHUN));
        $this->assertSame(1, $this->svc()->ringkasan(['tahun' => 2025])['selesai']);
        $this->assertSame(2, $this->svc()->ringkasan(['tahun' => 2026, 'tw' => 1])['selesai']); // v1, f1

        // tanggal_lhp: f1 by its LHP (Jul), the rest fall back to tanggal_selesai.
        app(Settings::class)->set('rekap.dasar_triwulan', 'tanggal_lhp');
        $this->assertSame($this->nol([0 => 1, 1 => 1, 5 => 1, 6 => 1]), $this->bulan(self::SETAHUN));
        $this->assertSame(1, $this->svc()->ringkasan(['tahun' => 2026, 'tw' => 3])['selesai']);
        $this->assertSame('TW III', collect($this->svc()->tabel(['tahun' => 2026, 'tw' => 3], 'desc', 1)->items())->first()['triwulan']);
    }

    public function test_irban_jenis_and_wilayah_filters(): void
    {
        $this->assertSame(1, $this->svc()->ringkasan(['tahun' => 2026, 'irban' => $this->a->id])['selesai']);
        $this->assertSame(0, $this->svc()->ringkasan(['tahun' => 2026, 'irban' => $this->c->id])['selesai']);
        $this->assertSame($this->nol([5 => 1]), $this->bulan(['tahun' => 2026, 'jenis' => $this->j2->id]));
        // Kec X matches v1 through its child Desa X1; Kec Y matches v2 directly.
        $this->assertSame($this->nol([1 => 1]), $this->bulan(['tahun' => 2026, 'wilayah' => $this->kecX->id]));
        $this->assertSame($this->nol([5 => 1]), $this->bulan(['tahun' => 2026, 'wilayah' => $this->kecY->id]));
        $this->assertSame(0, $this->svc()->ringkasan(['tahun' => 2026, 'wilayah' => $this->dinasD->id, 'irban' => $this->a->id])['selesai']);
    }

    public function test_ringkasan(): void
    {
        // items a1 + b1 = 2; rencana 2 + (null → 1) = 3; selesai 2 → 2/3 = 66,7 %.
        $this->assertSame(['selesai' => 2, 'item' => 2, 'rencana' => 3, 'persen' => 66.7], $this->svc()->ringkasan(self::SETAHUN));
        // Irban A: item a1, rencana 2, selesai v1 → 50 %.
        $this->assertSame(['selesai' => 1, 'item' => 1, 'rencana' => 2, 'persen' => 50.0], $this->svc()->ringkasan(['tahun' => 2026, 'irban' => $this->a->id]));
        // TW II: rencana only b1 (basis 30 Jun) = 1, selesai v2; item count ignores TW.
        $this->assertSame(['selesai' => 1, 'item' => 2, 'rencana' => 1, 'persen' => 100.0], $this->svc()->ringkasan(['tahun' => 2026, 'tw' => 2]));
        // Jenis J2: item b1 only.
        $this->assertSame(['selesai' => 1, 'item' => 1, 'rencana' => 1, 'persen' => 100.0], $this->svc()->ringkasan(['tahun' => 2026, 'jenis' => $this->j2->id]));
        // 2027 is Draf: nothing planned → persen null.
        $this->assertSame(['selesai' => 0, 'item' => 0, 'rencana' => 0, 'persen' => null], $this->svc()->ringkasan(['tahun' => 2027]));
    }

    public function test_per_bulan_per_irban_lists_every_irban(): void
    {
        $this->assertSame([
            ['irban' => 'Irban A', 'jumlah' => $this->nol([1 => 1])],
            ['irban' => 'Irban B', 'jumlah' => $this->nol([5 => 1])],
            ['irban' => 'Irban C', 'jumlah' => $this->nol()],
        ], $this->svc()->perBulan(self::SETAHUN, true));
        $this->assertSame([['irban' => 'Irban B', 'jumlah' => $this->nol([5 => 1])]], $this->svc()->perBulan(['tahun' => 2026, 'irban' => $this->b->id], true));
        $this->assertSame($this->nol(), $this->bulan(['tahun' => 2027]));
    }

    public function test_proporsi_keeps_six_slices_and_sums_the_rest(): void
    {
        // 8 extra jenis with 8, 7, …, 1 public SPT (36 total) under b1's PKPT; v1/v2 removed from the count by year 2025.
        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2025]);
        $item = PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $this->c->id]);
        foreach (range(8, 1) as $n) {
            $jenis = JenisPenugasan::factory()->create(['nama' => "Jenis K$n", 'warna' => sprintf('#0000%02d', $n)]);
            for ($i = 0; $i < $n; $i++) {
                $this->spt('selesai', $this->c, $item, $this->reguler, '2025-05-01', '2025-05-02', [], $jenis->id);
            }
        }

        $p = $this->svc()->proporsi(['tahun' => 2025], 'jenis');
        // 8/36 = 22,2; 7/36 = 19,4; 6/36 = 16,7; 5/36 = 13,9; 4/36 = 11,1; 3/36 = 8,3; Lainnya (2 + 1) / 36 = 8,3.
        $this->assertSame(['Jenis K8', 'Jenis K7', 'Jenis K6', 'Jenis K5', 'Jenis K4', 'Jenis K3', 'Lainnya'], array_column($p, 'label'));
        $this->assertSame([8, 7, 6, 5, 4, 3, 3], array_column($p, 'jumlah'));
        $this->assertSame([22.2, 19.4, 16.7, 13.9, 11.1, 8.3, 8.3], array_column($p, 'persen'));
        $this->assertSame(['#000008', null], [$p[0]['warna'], $p[6]['warna']]);

        // Irban: only C has SPT in 2025; its colour index is its position among all irban by name (A 0, B 1, C 2).
        $this->assertSame([['label' => 'Irban C', 'jumlah' => 36, 'persen' => 100.0, 'warna' => 2]], $this->svc()->proporsi(['tahun' => 2025], 'irban'));
        $this->assertSame([], $this->svc()->proporsi(['tahun' => 2024], 'irban'));
    }

    public function test_rencana_vs_realisasi(): void
    {
        $setahun = ['periode' => 'Setahun', 'label' => ['Irban A', 'Irban B', 'Irban C'], 'rencana' => [2, 1, 0], 'realisasi' => [1, 1, 0], 'persen' => [50, 100, null]];
        $this->assertSame($setahun, $this->svc()->rencanaVsRealisasi(self::SETAHUN, null));
        // Bulan and wilayah are ignored.
        $this->assertSame($setahun, $this->svc()->rencanaVsRealisasi(['tahun' => 2026, 'bulan' => 6, 'wilayah' => $this->kecX->id], null));
        // TW I: a1 (basis 31 Mar) + v1; TW II: b1 (30 Jun) + v2.
        $this->assertSame(['TW I', [2, 0, 0], [1, 0, 0]], $this->pick($this->svc()->rencanaVsRealisasi(self::SETAHUN, 1)));
        $this->assertSame(['TW II', [0, 1, 0], [0, 1, 0]], $this->pick($this->svc()->rencanaVsRealisasi(self::SETAHUN, 2)));
        $this->assertSame(['Setahun', [0, 1, 0], [0, 1, 0]], $this->pick($this->svc()->rencanaVsRealisasi(['tahun' => 2026, 'jenis' => $this->j2->id], null)));
        $this->assertSame(['Irban A'], $this->svc()->rencanaVsRealisasi(['tahun' => 2026, 'irban' => $this->a->id], null)['label']);
    }

    private function pick(array $g): array
    {
        return [$g['periode'], $g['rencana'], $g['realisasi']];
    }

    public function test_tabel_sorts_paginates_and_formats_ranges(): void
    {
        $this->spt('selesai', $this->a, $this->a1, $this->reguler, '2025-12-29', '2026-01-03'); // basis 3 Jan 2026

        $desc = $this->svc()->tabel(self::SETAHUN, 'desc', 1)->items();
        $this->assertSame(['28 Mei – 2 Juni 2026', '3–20 Februari 2026', '29 Desember 2025 – 3 Januari 2026'], array_column($desc, 'tanggal'));
        $this->assertSame(['TW II', 'TW I', 'TW I'], array_column($desc, 'triwulan'));
        $asc = $this->svc()->tabel(self::SETAHUN, 'asc', 1)->items();
        $this->assertSame(array_reverse(array_column($desc, 'tanggal')), array_column($asc, 'tanggal'));
        $this->assertSame('Belum ditentukan', $asc[0]['wilayah']); // no objek

        // 23 more → 26 rows: 25 on page 1, 1 on page 2.
        for ($i = 0; $i < 23; $i++) {
            $this->spt('selesai', $this->b, $this->b1, $this->reguler, '2026-04-01', '2026-04-01');
        }
        $h1 = $this->svc()->tabel(self::SETAHUN, 'desc', 1);
        $this->assertSame([26, 25, 2], [$h1->total(), count($h1->items()), $h1->lastPage()]);
        $this->assertSame(['29 Desember 2025 – 3 Januari 2026'], array_column($this->svc()->tabel(self::SETAHUN, 'desc', 2)->items(), 'tanggal'));
        $this->assertSame('1 April 2026', $h1->items()[1]['tanggal']); // one-day range (after v2, 2 Juni)
    }

    public function test_rencana_matches_the_pimpinan_rekap(): void
    {
        // Extra items: C 1–31 Jul revised (approved) to 28 Sep – 2 Okt (TW III by start, TW IV by end), target 3;
        // C Des 2026 revised (approved) into Jan 2027 → outside 2026 by end date, inside by start date.
        $c1 = PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->c->id, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-31', 'target_spt' => 3]);
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $c1->id, 'tanggal_mulai' => '2026-09-28', 'tanggal_selesai' => '2026-10-02']);
        $c2 = PkptItem::factory()->create(['pkpt_id' => $this->a1->pkpt_id, 'irban_id' => $this->c->id, 'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2026-12-20']);
        PkptItemRevisi::factory()->disetujui()->create(['pkpt_item_id' => $c2->id, 'tanggal_mulai' => '2026-12-28', 'tanggal_selesai' => '2027-01-08']);

        foreach (['tanggal_selesai', 'tanggal_mulai', 'tanggal_lhp'] as $dasar) {
            app(Settings::class)->set('rekap.dasar_triwulan', $dasar);
            $rekap = app(RekapTriwulan::class)->hitung(2026)['irban']; // irban ordered by id = A, B, C = by name here
            foreach ([null, 1, 2, 3, 4] as $tw) {
                $pakai = fn ($sel) => $tw ? $sel['tw'][$tw]['rencana'] : $sel['total']['rencana'];
                $this->assertSame(array_map($pakai, $rekap['baris']), $this->svc()->rencanaVsRealisasi(self::SETAHUN, $tw)['rencana'], "$dasar TW $tw");
                $this->assertSame($pakai($rekap['total']), $this->svc()->ringkasan(['tahun' => 2026, 'tw' => $tw])['rencana'], "$dasar TW $tw");
            }
        }
    }

    public function test_opsi(): void
    {
        Pkpt::factory()->disahkan()->create(['tahun' => 2024]);
        $o = $this->svc()->opsi();
        $this->assertSame([2026, 2024], $o['tahun']); // 2027 is Draf
        $this->assertSame(2026, $o['tahunDefault']);
        $this->assertSame(['Irban A', 'Irban B', 'Irban C'], array_column($o['irban'], 'nama'));
        $this->assertSame(['id', 'nama'], array_keys($o['irban'][0]));
        $this->assertSame(['Kec X', 'Kec Y'], array_column($o['wilayah'], 'nama'));
        $this->assertSame([['id' => $this->j2->id, 'nama' => 'Jenis Dua', 'warna' => '#B54708', 'warna_teks' => '#FFFFFF'], ['id' => $this->j1->id, 'nama' => 'Jenis Satu', 'warna' => '#3F6FA6', 'warna_teks' => '#FFFFFF']], $o['jenis']);

        Pkpt::where('tahun', 2026)->update(['status' => 'draf']);
        $this->assertSame([[2024], 2024], [$this->svc()->opsi()['tahun'], $this->svc()->opsi()['tahunDefault']]); // latest when current is absent
        Pkpt::where('tahun', 2024)->update(['status' => 'draf']);
        $this->assertSame([[2026], 2026], [$this->svc()->opsi()['tahun'], $this->svc()->opsi()['tahunDefault']]); // none → current year
    }
}
