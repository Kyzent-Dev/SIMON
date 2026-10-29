<?php

namespace Tests\Feature\Rekap;

use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Livewire\Admin\PkptDetail;
use App\Livewire\Rekap;
use App\Models\Irban;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\Spt;
use App\Models\User;
use App\Services\Ekspor\PembangunRekap;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/** 1.1.4 Task 6 (K9): items without target_spt are visible in PKPT detail, the Sahkan modal and the Rekap page/files; >100% is marked. */
class TargetSptTest extends TestCase
{
    use RefreshDatabase;

    private Irban $a;

    private Irban $b;

    private PkptItem $a1;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-12-15 12:00', 'Asia/Jakarta'));
        app(Settings::class)->set('rekap.dasar_triwulan', 'tanggal_selesai');
        $this->a = Irban::factory()->create(['nama' => 'Irban A']);
        $this->b = Irban::factory()->create(['nama' => 'Irban B']);
        $this->pimpinan = User::factory()->create(['role' => Role::Pimpinan]);

        $pkpt = Pkpt::factory()->disahkan()->create(['tahun' => 2026]);
        $item = fn (Irban $i, string $m, string $s, ?int $t, array $lain = []) => PkptItem::factory()->disahkanDari()->create(
            ['pkpt_id' => $pkpt->id, 'irban_id' => $i->id, 'tanggal_mulai' => $m, 'tanggal_selesai' => $s, 'target_spt' => $t, ...$lain]);
        $this->a1 = $item($this->a, '2026-02-02', '2026-02-10', null);         // TW I, no target
        $item($this->a, '2026-05-04', '2026-05-08', 2);                         // TW II, target 2
        $item($this->b, '2026-08-03', '2026-08-07', null);                      // TW III, no target
        $item($this->a, '2026-03-02', '2026-03-06', null)->forceFill(['status' => 'dihapus'])->save(); // removed: never counted
        PkptItem::factory()->create(['pkpt_id' => Pkpt::factory()->create(['tahun' => 2027])->id, 'irban_id' => $this->a->id, 'target_spt' => null]); // draft PKPT

        // Two finished SPT on a1 (rencana 1): 200% in TW I for Irban A.
        foreach (['2026-02-03', '2026-02-09'] as $tgl) {
            Spt::factory()->selesai()->create(['irban_id' => $this->a->id, 'pkpt_item_id' => $this->a1->id, 'jenis_penugasan_id' => $this->a1->jenis_penugasan_id,
                'tanggal_mulai' => $tgl, 'tanggal_selesai' => $tgl]);
        }
    }

    private function semuaBerTarget(): void
    {
        PkptItem::query()->whereNull('target_spt')->get()->each->update(['target_spt' => 2]);
    }

    private function buku(array $filter): Spreadsheet
    {
        $p = app(PembangunRekap::class);
        $path = tempnam(sys_get_temp_dir(), 'rekap').'.xlsx';
        file_put_contents($path, Excel::raw($p->export($this->pimpinan, $p->filter($this->pimpinan, ['tahun' => 2026, ...$filter])), ExcelWriter::XLSX));
        $buku = IOFactory::load($path);
        @unlink($path);

        return $buku;
    }

    private function teks(Spreadsheet $buku, string $sheet): array
    {
        return array_values(array_filter(array_merge(...$buku->getSheetByName($sheet)->toArray(null, true, false, false)), fn ($c) => $c !== null && $c !== ''));
    }

    private function pdf(array $filter): string
    {
        $p = app(PembangunRekap::class);

        return $p->html($this->pimpinan, $p->filter($this->pimpinan, ['tahun' => 2026, ...$filter]));
    }

    public function test_count_per_pkpt_ignores_removed_items(): void
    {
        $this->assertSame(2, Pkpt::where('tahun', 2026)->first()->jumlahTanpaTarget());
        $this->assertSame(1, Pkpt::where('tahun', 2027)->first()->jumlahTanpaTarget());
    }

    public function test_count_per_period_follows_year_quarter_and_irban(): void
    {
        $p = app(PembangunRekap::class);
        $this->assertSame(2, $p->tanpaTarget(2026, 0, null));
        $this->assertSame(1, $p->tanpaTarget(2026, 1, null));
        $this->assertSame(0, $p->tanpaTarget(2026, 2, null));
        $this->assertSame(1, $p->tanpaTarget(2026, 0, $this->a->id));
        $this->assertSame(1, $p->tanpaTarget(2026, 3, $this->b->id));
        $this->assertSame(0, $p->tanpaTarget(2026, 3, $this->a->id));
        $this->assertSame(0, $p->tanpaTarget(2027, 0, null)); // the 2027 PKPT is a draft
    }

    public function test_pkpt_detail_summary_marker_filter_and_help_text(): void
    {
        $admin = User::factory()->admin()->create();
        $pkpt = Pkpt::where('tahun', 2026)->first();
        Livewire::actingAs($admin)->test(PkptDetail::class, ['pkpt' => $pkpt])
            ->assertSee('2 item belum punya target SPT')
            ->assertSeeHtml('</svg>Target kosong')
            ->assertViewHas('items', fn ($items) => $items->count() === 3)
            ->set('targetKosong', true)
            ->assertViewHas('items', fn ($items) => $items->pluck('target_spt')->unique()->all() === [null] && $items->count() === 2)
            ->assertSee('2 dari 3 item');

        $draf = Pkpt::where('tahun', 2027)->first();
        Livewire::actingAs($admin)->test(PkptDetail::class, ['pkpt' => $draf])
            ->assertSeeHtml('id="baru-target-bantuan"')
            ->assertSee('Perkiraan jumlah SPT untuk item ini. Kosong dihitung 1.');
    }

    public function test_sahkan_warns_about_missing_targets_without_blocking(): void
    {
        $pkpt = Pkpt::where('tahun', 2027)->first();
        $pkpt->update(['nomor_sk' => '100/1/2027', 'tanggal_sk' => '2027-01-02']);
        PkptItem::factory()->create(['pkpt_id' => $pkpt->id, 'irban_id' => $this->a->id, 'target_spt' => 3]);

        Livewire::actingAs(User::factory()->admin()->create())->test(PkptDetail::class, ['pkpt' => $pkpt])
            ->set('sahkanOpen', true)
            ->assertSee('1 item belum punya target SPT. Rencana di rekap akan dihitung 1 per item.')
            ->call('sahkan')
            ->assertHasNoErrors();
        $this->assertSame(StatusPkpt::Disahkan, $pkpt->fresh()->status);
    }

    public function test_sahkan_modal_has_no_target_warning_when_every_item_has_one(): void
    {
        $pkpt = Pkpt::where('tahun', 2027)->first();
        $this->semuaBerTarget();
        Livewire::actingAs(User::factory()->admin()->create())->test(PkptDetail::class, ['pkpt' => $pkpt])
            ->set('sahkanOpen', true)
            ->assertDontSee('belum punya target SPT');
    }

    public function test_rekap_page_note_and_over_100_marker(): void
    {
        Livewire::actingAs($this->pimpinan)->test(Rekap::class)->set('tahun', '2026')
            ->assertSee(PembangunRekap::CATATAN_TARGET)
            ->assertSee(PembangunRekap::CATATAN_LEBIH)
            ->assertSeeHtml('di atas 100%, lihat catatan')
            ->set('tw', '2') // no untargeted item and no percentage above 100 in TW II
            ->assertDontSee(PembangunRekap::CATATAN_TARGET)
            ->assertDontSee(PembangunRekap::CATATAN_LEBIH);
    }

    public function test_rekap_page_has_no_notes_when_targets_are_filled(): void
    {
        $this->semuaBerTarget(); // a1: 2 of 2 = 100%
        Livewire::actingAs($this->pimpinan)->test(Rekap::class)->set('tahun', '2026')
            ->assertDontSee(PembangunRekap::CATATAN_TARGET)
            ->assertDontSee(PembangunRekap::CATATAN_LEBIH)
            ->assertDontSeeHtml('lihat catatan');
    }

    public function test_pdf_notes_only_when_relevant(): void
    {
        $html = $this->pdf(['bagian' => ['rr']]);
        $this->assertStringContainsString(PembangunRekap::CATATAN_TARGET, $html);
        $this->assertStringContainsString(e(PembangunRekap::CATATAN_LEBIH), $html);
        $this->assertStringContainsString('200 *', $html);
        // Without Rencana vs Realisasi the target note moves under Per Jenis Penugasan; Per Klasifikasi alone has no rencana.
        $this->assertStringContainsString(PembangunRekap::CATATAN_TARGET, $this->pdf(['bagian' => ['jenis']]));
        $this->assertStringNotContainsString(PembangunRekap::CATATAN_TARGET, $this->pdf(['bagian' => ['klasifikasi']]));

        $this->semuaBerTarget();
        $html = $this->pdf(['bagian' => ['rr', 'jenis']]);
        $this->assertStringNotContainsString(PembangunRekap::CATATAN_TARGET, $html);
        $this->assertStringNotContainsString(e(PembangunRekap::CATATAN_LEBIH), $html);
    }

    /** The target note sits right under Rencana vs Realisasi (Gabungan: under Ringkasan per Irban); the >100% footnote closes the summary. */
    public function test_excel_target_note_directly_under_rencana_vs_realisasi(): void
    {
        $buku = $this->buku(['bagian' => ['rr', 'klasifikasi', 'jenis']]);
        $kolomA = fn (string $sheet) => array_column($buku->getSheetByName($sheet)->toArray(null, true, false, false), 0);

        $a = $kolomA('Gabungan');
        $ringkasan = array_search('Ringkasan per Irban', $a, true);
        $catatan = $ringkasan + 2 + 2 + 1; // title, header, two irban rows, empty row
        $this->assertSame(['Catatan', 'Keterangan', PembangunRekap::CATATAN_TARGET, null, 'Per Klasifikasi'], array_slice($a, $catatan, 5));
        $this->assertSame(PembangunRekap::CATATAN_LEBIH, end($a));

        $a = $kolomA('Irban A');
        $rr = array_search('Rencana vs Realisasi', $a, true);
        $this->assertSame(['Catatan', 'Keterangan', PembangunRekap::CATATAN_TARGET, null, 'Per Klasifikasi'], array_slice($a, $rr + 2 + 5 + 1, 5)); // TW I–IV + Total

        // Without Rencana vs Realisasi the note follows Per Jenis Penugasan, as in the PDF.
        $a = array_column($this->buku(['bagian' => ['klasifikasi', 'jenis']])->getSheetByName('Gabungan')->toArray(null, true, false, false), 0);
        $this->assertGreaterThan(array_search('Per Jenis Penugasan', $a, true), array_search(PembangunRekap::CATATAN_TARGET, $a, true));
    }

    public function test_excel_catatan_section_only_when_relevant_per_sheet(): void
    {
        $buku = $this->buku(['bagian' => ['rr']]);
        $gabungan = $this->teks($buku, 'Gabungan');
        $this->assertContains('Catatan', $gabungan);
        $this->assertContains(PembangunRekap::CATATAN_TARGET, $gabungan);
        $this->assertContains(PembangunRekap::CATATAN_LEBIH, $gabungan);
        $this->assertContains('200 *', $this->teks($buku, 'Irban A')); // TW I row of Irban A
        $this->assertContains(PembangunRekap::CATATAN_TARGET, $this->teks($buku, 'Irban B'));
        $this->assertNotContains(PembangunRekap::CATATAN_LEBIH, $this->teks($buku, 'Irban B'));

        // TW II: no untargeted item, no percentage above 100 → no Catatan section anywhere.
        $buku = $this->buku(['bagian' => ['rr', 'jenis'], 'tw' => 2]);
        foreach ($buku->getSheetNames() as $nama) {
            $this->assertNotContains('Catatan', $this->teks($buku, $nama), $nama);
        }

        $this->semuaBerTarget();
        $buku = $this->buku(['bagian' => ['rr', 'jenis']]);
        foreach ($buku->getSheetNames() as $nama) {
            $this->assertNotContains('Catatan', $this->teks($buku, $nama), $nama);
        }
    }
}
