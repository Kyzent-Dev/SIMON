<?php

namespace Tests\Feature\Spt;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpt;
use App\Models\Objek;
use App\Models\Spt;
use App\Services\Settings;
use App\Support\Tanggal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusSptTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_follows_status_pengajuan(): void
    {
        $this->assertSame(StatusSpt::Draf, Spt::factory()->create()->status());
        $this->assertSame(StatusSpt::MenungguNomor, Spt::factory()->menungguNomor()->create()->status());
        $this->assertSame(StatusSpt::PerluRevisi, Spt::factory()->perluRevisi()->create()->status());
        $this->assertSame(StatusSpt::Ditolak, Spt::factory()->ditolak()->create()->status());
        $this->assertSame(StatusSpt::Dibatalkan, Spt::factory()->dibatalkan()->create()->status());
        $this->assertSame(StatusPengajuan::Draf, Spt::factory()->create()->fresh()->status_pengajuan);
    }

    public function test_diterima_status_depends_on_today_with_inclusive_edges(): void
    {
        $spt = Spt::factory()->diterima()->create(['tanggal_mulai' => '2026-03-02', 'tanggal_selesai' => '2026-03-20'])->fresh();

        $cases = [
            '2026-03-01 23:59:59' => StatusSpt::SiapDilaksanakan,
            '2026-03-02 00:00:00' => StatusSpt::Pelaksanaan,
            '2026-03-20 23:59:59' => StatusSpt::Pelaksanaan,
            '2026-03-21 00:00:00' => StatusSpt::PenyusunanLaporan,
        ];
        foreach ($cases as $now => $expected) {
            $this->travelTo(CarbonImmutable::parse($now, 'Asia/Jakarta'));
            $this->assertSame($expected, $spt->status(), $now);
        }
    }

    public function test_labels_and_icons_follow_design(): void
    {
        $this->assertSame(
            ['Draf', 'Menunggu Nomor', 'Perlu Revisi', 'Ditolak', 'Siap Dilaksanakan', 'Pelaksanaan', 'Penyusunan Laporan', 'Selesai', 'Dibatalkan'],
            array_map(fn ($s) => $s->label(), StatusSpt::cases()),
        );
        $this->assertSame('clock', StatusSpt::MenungguNomor->icon());
        $this->assertSame('no-symbol', StatusSpt::Dibatalkan->icon());
    }

    public function test_durasi_kalender_and_kerja(): void
    {
        // Fri 2026-03-06 .. Tue 2026-03-10: 5 calendar days, 3 working days.
        $this->assertSame(5, Tanggal::durasi('2026-03-06', '2026-03-10'));
        $this->assertSame(3, Tanggal::durasi('2026-03-06', '2026-03-10', kerja: true));
        $this->assertSame(1, Tanggal::durasi('2026-03-07', '2026-03-07'));
        $this->assertSame(0, Tanggal::durasi('2026-03-07', '2026-03-08', kerja: true));

        $spt = Spt::factory()->create(['tanggal_mulai' => '2026-03-06', 'tanggal_selesai' => '2026-03-10'])->fresh();
        $this->assertSame(5, $spt->durasi());
        app(Settings::class)->set('penugasan.hitungan_hari', 'kerja');
        $this->assertSame(3, $spt->durasi());
        $this->assertNull(Spt::factory()->create(['tanggal_mulai' => null, 'tanggal_selesai' => null])->durasi());
    }

    public function test_relations(): void
    {
        $spt = Spt::factory()->create();
        $objek = Objek::factory()->count(2)->create();
        $spt->objek()->attach($objek->pluck('id'));

        $this->assertEqualsCanonicalizing($objek->pluck('id')->all(), $spt->fresh()->objek->pluck('id')->all());
        $this->assertNotNull($spt->irban);
        $this->assertNotNull($spt->klasifikasi);
        $this->assertNotNull($spt->dibuatOleh);
    }
}
