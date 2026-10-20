<?php

namespace Tests\Feature\Dasbor;

use App\Enums\KejadianSpt;
use App\Enums\Role;
use App\Enums\StatusPermintaan;
use App\Livewire\Irban\Dasbor;
use App\Livewire\Irban\SptSaya;
use App\Models\Irban;
use App\Models\Komentar;
use App\Models\Spt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Dasbor Irban (Fase 8, Task 6), today 15 Juni 2026 (Jakarta), batas terlambat 14 hari. Irban A's SPT ("year" = SPT Saya's rule: year of
 * tanggal_mulai, or of created_at for drafts without dates):
 *   2026: d1, d2 Draf · m1 Menunggu Nomor · r1, r2 Perlu Revisi · b1 Pelaksanaan (1–30 Jun) · b2 PL 1–20 Mei (26 hari, terlambat)
 *         · b3 PL 1–10 Jun (5 hari, tidak) · b4 PL 1–10 Apr (66 hari, terlambat) · b5 Siap (Jul) · s1 Selesai · x1 Dibatalkan · x2 Ditolak
 *   2025: r3 Perlu Revisi · lama PL 1–10 Jan 2025 (521 hari, terlambat)
 * Cards (2026): Draf 2, Menunggu Nomor 1, Perlu Revisi 2, Berjalan b1–b5 = 5, Selesai 1, Terlambat b2+b4 = 2.
 * Perlu Tindakan (all years): permintaan p2 (on s1, 1 Jun = 14 hari), p1 (on b1, 12 Jun = 3 hari); Perlu Revisi r3 (20 Des 2025 = 177 hari),
 * r2 (1 Jun = 14), r1 (10 Jun = 5); Terlambat lama (521), b4 (66), b2 (26). Irban B's items, a closed permintaan and replies never show.
 */
class DasborIrbanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Irban $a;

    /** @var array<string, Spt> */
    private array $s = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        $this->a = Irban::factory()->create(['nama' => 'Irban A']);
        $b = Irban::factory()->create();
        $this->user = User::factory()->irban()->create(['irban_id' => $this->a->id]);

        $buat = fn ($f, array $attr = []) => $f->create(['irban_id' => $this->a->id, ...$attr]);
        $this->s['d1'] = $buat(Spt::factory());
        $this->s['d2'] = $buat(Spt::factory(), ['tanggal_mulai' => null, 'tanggal_selesai' => null]);
        $this->s['m1'] = $buat(Spt::factory()->menungguNomor());
        $this->s['r1'] = $buat(Spt::factory()->perluRevisi());
        $this->s['r2'] = $buat(Spt::factory()->perluRevisi());
        $this->s['b1'] = $buat(Spt::factory()->diterima(), ['tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-30']);
        $this->s['b2'] = $buat(Spt::factory()->diterima(), ['tanggal_mulai' => '2026-05-01', 'tanggal_selesai' => '2026-05-20']);
        $this->s['b3'] = $buat(Spt::factory()->diterima(), ['tanggal_mulai' => '2026-06-01', 'tanggal_selesai' => '2026-06-10']);
        $this->s['b4'] = $buat(Spt::factory()->diterima(), ['tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-10']);
        $this->s['b5'] = $buat(Spt::factory()->diterima(), ['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2026-07-10']);
        $this->s['s1'] = $buat(Spt::factory()->selesai());
        $this->s['x1'] = $buat(Spt::factory()->dibatalkan());
        $this->s['x2'] = $buat(Spt::factory()->ditolak());
        $this->s['r3'] = $buat(Spt::factory()->perluRevisi(), ['tanggal_mulai' => '2025-12-01', 'tanggal_selesai' => '2025-12-10']);
        $this->s['lama'] = $buat(Spt::factory()->diterima(), ['tanggal_mulai' => '2025-01-01', 'tanggal_selesai' => '2025-01-10']);

        foreach (['r1' => '2026-06-10 09:00', 'r2' => '2026-06-01 09:00', 'r3' => '2025-12-20 09:00'] as $k => $waktu) {
            // An older perlu_revisi round first: the age counts from the latest one.
            $this->s[$k]->riwayat()->create(['kejadian' => KejadianSpt::PERLU_REVISI, 'alasan' => 'Lama sekali.', 'oleh' => $this->user->id])
                ->forceFill(['created_at' => '2025-01-01 08:00'])->save();
            $this->s[$k]->riwayat()->create(['kejadian' => KejadianSpt::PERLU_REVISI, 'alasan' => 'Perbaiki personel.', 'oleh' => $this->user->id])
                ->forceFill(['created_at' => $waktu])->save();
        }

        $p1 = Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $this->s['b1']->id]);
        $p1->forceFill(['created_at' => '2026-06-12 08:00'])->save();
        Komentar::factory()->balasanDari($p1)->create();
        $p2 = Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $this->s['s1']->id]);
        $p2->forceFill(['created_at' => '2026-06-01 08:00'])->save();
        Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $this->s['b1']->id, 'status_permintaan' => StatusPermintaan::Selesai]);
        $this->s['p1'] = $p1;
        $this->s['p2'] = $p2;

        // Irban B: nothing of this may show for Irban A.
        $lain = Spt::factory()->diterima()->create(['irban_id' => $b->id, 'tanggal_mulai' => '2026-04-01', 'tanggal_selesai' => '2026-04-10']);
        Spt::factory()->perluRevisi()->create(['irban_id' => $b->id]);
        Spt::factory()->create(['irban_id' => $b->id]);
        Komentar::factory()->mintaPenjelasan()->create(['spt_id' => $lain->id]);
    }

    private function halaman()
    {
        return Livewire::actingAs($this->user)->test(Dasbor::class);
    }

    public function test_route_is_the_livewire_page_and_other_roles_are_forbidden(): void
    {
        $this->actingAs($this->user)->get('/irban')->assertOk()->assertSeeLivewire(Dasbor::class)
            ->assertSee('Irban A · Tahun 2026')->assertSee('Ajukan Nomor SPT');
        foreach ([Role::Pimpinan, Role::BagianUmum, Role::Admin] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/irban')->assertForbidden();
            Livewire::actingAs($u)->test(Dasbor::class)->assertForbidden();
        }
        Livewire::actingAs(User::factory()->irban()->create(['irban_id' => null]))->test(Dasbor::class)->assertForbidden();
    }

    public function test_perlu_tindakan_contents_and_urgency_order(): void
    {
        $tindakan = $this->halaman()->viewData('tindakan');
        $s = $this->s;

        $this->assertSame([
            ['permintaan', $s['s1']->id, 14], ['permintaan', $s['b1']->id, 3],
            ['perlu_revisi', $s['r3']->id, 177], ['perlu_revisi', $s['r2']->id, 14], ['perlu_revisi', $s['r1']->id, 5],
            ['terlambat', $s['lama']->id, 521], ['terlambat', $s['b4']->id, 66], ['terlambat', $s['b2']->id, 26],
        ], array_map(fn ($t) => [$t['jenis'], $t['spt']->id, $t['umur']], $tindakan));

        $this->assertSame(route('spt.show', $s['s1']).'#komentar-'.$s['p2']->id, $tindakan[0]['href']);
        $this->assertSame(route('irban.spt.ubah', $s['r3']), $tindakan[2]['href']);
        $this->assertSame(route('spt.show', $s['lama']), $tindakan[5]['href']);
    }

    public function test_perlu_tindakan_empty_state(): void
    {
        $kosong = User::factory()->irban()->create(['irban_id' => Irban::factory()->create()->id]);
        Livewire::actingAs($kosong)->test(Dasbor::class)
            ->assertSee('Tidak ada yang perlu ditindaklanjuti. Kerja bagus!')
            ->assertViewHas('tindakan', []);
    }

    public function test_stat_cards_are_scoped_to_the_irban_and_link_to_spt_saya_filters(): void
    {
        $kartu = $this->halaman()->viewData('kartu');

        $this->assertSame(['Draf', 'Menunggu Nomor', 'Perlu Revisi', 'Berjalan', 'Selesai', 'Terlambat'], array_column($kartu, 'label'));
        $this->assertSame([2, 1, 2, 5, 1, 2], array_column($kartu, 'nilai'));
        // SPT Saya has a single-status filter: Berjalan (three statuses) and Terlambat (a flag) are not links.
        $this->assertSame([
            route('irban.spt', ['status' => 'draf', 'tahun' => 2026]), route('irban.spt', ['status' => 'menunggu_nomor', 'tahun' => 2026]),
            route('irban.spt', ['status' => 'perlu_revisi', 'tahun' => 2026]), null, route('irban.spt', ['status' => 'selesai', 'tahun' => 2026]), null,
        ], array_column($kartu, 'href'));
    }

    public function test_a_card_number_equals_the_spt_saya_rows_after_clicking_it(): void
    {
        foreach (['draf' => 2, 'menunggu_nomor' => 1, 'perlu_revisi' => 2, 'selesai' => 1] as $status => $n) {
            Livewire::withQueryParams(['status' => $status, 'tahun' => 2026])->actingAs($this->user)
                ->test(SptSaya::class)->assertViewHas('spt', fn ($p) => $p->total() === $n);
        }
    }

    public function test_spt_saya_shows_the_recent_ten_of_the_year(): void
    {
        $tahunIni = collect($this->s)->only(['d1', 'd2', 'm1', 'r1', 'r2', 'b1', 'b2', 'b3', 'b4', 'b5', 's1', 'x1', 'x2'])
            ->map->id->sortDesc()->take(10)->values()->all();
        $h = $this->halaman();

        // All rows share one updated_at (frozen clock), so the newest id comes first.
        $this->assertSame($tahunIni, $h->viewData('terbaru')->pluck('id')->all());
        $h->assertSee('Lihat semua')->assertSee(route('irban.spt'), false)->assertSee(route('irban.rekap'), false);
    }

    public function test_mini_chart_data_holds_the_disjoint_status_counts(): void
    {
        $this->assertSame([
            'tahun' => 2026,
            'label' => ['Draf', 'Menunggu Nomor', 'Perlu Revisi', 'Berjalan', 'Selesai'],
            'nilai' => [2, 1, 2, 5, 1],
        ], $this->halaman()->viewData('grafik'));
    }
}
