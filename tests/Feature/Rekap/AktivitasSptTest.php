<?php

namespace Tests\Feature\Rekap;

use App\Enums\KejadianSpt;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\SptRiwayat;
use App\Services\AktivitasSpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AktivitasSptTest extends TestCase
{
    use RefreshDatabase;

    private function kejadian(string $kejadian, ?Spt $spt = null, string $waktu = '2026-11-03 10:00:00'): SptRiwayat
    {
        $spt ??= Spt::factory()->diterima()->create(['nomor_lengkap' => '700/'.Str::random(6).'/2026', 'irban_id' => Irban::firstWhere('nama', 'Irban Z')?->id ?? Irban::factory()->create(['nama' => 'Irban Z'])->id]);
        $r = SptRiwayat::create(['spt_id' => $spt->id, 'kejadian' => $kejadian, 'oleh' => $spt->dibuat_oleh]);
        $r->forceFill(['created_at' => $waktu])->save();

        return $r;
    }

    public function test_returns_latest_events_newest_first_capped(): void
    {
        $ids = [];
        for ($i = 0; $i < 8; $i++) {
            $ids[] = $this->kejadian(KejadianSpt::DITERIMA, null, $i < 2 ? '2026-11-01 08:00:00' : '2026-11-03 10:00:00')->id;
        }

        $hasil = app(AktivitasSpt::class)->terbaru();

        $this->assertCount(6, $hasil);
        // Same created_at (the six newest) is broken by id descending; the two oldest events drop out.
        $this->assertSame(array_reverse(array_slice($ids, 2)), array_column($hasil, 'id'));
        $this->assertSame('Nomor diterbitkan', $hasil[0]['label']);
        $this->assertSame(Spt::find(SptRiwayat::find(end($ids))->spt_id)->nomor_lengkap, $hasil[0]['nomor']);
        $this->assertSame('Irban Z', $hasil[0]['irban']);
        $this->assertSame(route('spt.show', SptRiwayat::find(end($ids))->spt_id), $hasil[0]['url']);
        $this->assertSame('2026-11-03 10:00', $hasil[0]['waktu']->format('Y-m-d H:i'));
    }

    public function test_only_tracked_events(): void
    {
        foreach ([KejadianSpt::DITERIMA, KejadianSpt::DICATAT_MANUAL, KejadianSpt::DIBATALKAN, KejadianSpt::FINAL_DIUNGGAH, KejadianSpt::KENDALA_DIISI, KejadianSpt::DITANDAI_SELESAI] as $k) {
            $this->kejadian($k);
        }
        foreach ([KejadianSpt::DIBUAT, KejadianSpt::DIUBAH, KejadianSpt::DIAJUKAN, KejadianSpt::SPPD_DIUBAH, KejadianSpt::DIIMPOR] as $k) {
            $this->kejadian($k);
        }

        $labels = array_column(app(AktivitasSpt::class)->terbaru(20), 'label');

        $this->assertEqualsCanonicalizing(['Nomor diterbitkan', 'Nomor dicatat manual', 'Nomor dibatalkan', 'SPT final diunggah', 'Kendala diisi', 'Ditandai Selesai'], $labels);
    }

    public function test_query_count_is_constant(): void
    {
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(AktivitasSpt::class)->terbaru();

            return count(DB::getQueryLog());
        };
        for ($i = 0; $i < 2; $i++) {
            $this->kejadian(KejadianSpt::DITERIMA);
        }
        $dua = $hitung();
        for ($i = 0; $i < 4; $i++) {
            $this->kejadian(KejadianSpt::DITERIMA);
        }

        $this->assertSame($dua, $hitung());
    }

    public function test_spt_without_number_falls_back(): void
    {
        $this->kejadian(KejadianSpt::DIBATALKAN, Spt::factory()->create(['nomor_lengkap' => null]));

        $this->assertSame('Nomor belum terbit', app(AktivitasSpt::class)->terbaru()[0]['nomor']);
    }
}
