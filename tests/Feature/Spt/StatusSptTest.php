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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** @return array<string, array{0: StatusPengajuan, 1: ?string, 2: ?string, 3: bool, 4: string, 5: StatusSpt}> pengajuan, mulai, selesai, ditandai selesai, now, expected */
    public static function matriksStatus(): array
    {
        $d = StatusPengajuan::Diterima;
        $jkt = fn (string $t) => $t.' Asia/Jakarta';
        $cases = [
            'sebelum mulai' => [$d, '2026-03-10', '2026-03-20', false, $jkt('2026-03-09 12:00'), StatusSpt::SiapDilaksanakan],
            'hari mulai' => [$d, '2026-03-10', '2026-03-20', false, $jkt('2026-03-10 12:00'), StatusSpt::Pelaksanaan],
            'hari selesai' => [$d, '2026-03-10', '2026-03-20', false, $jkt('2026-03-20 12:00'), StatusSpt::Pelaksanaan],
            'sehari setelah selesai' => [$d, '2026-03-10', '2026-03-20', false, $jkt('2026-03-21 12:00'), StatusSpt::PenyusunanLaporan],
            'mulai kosong' => [$d, null, null, false, $jkt('2026-03-15 12:00'), StatusSpt::SiapDilaksanakan],
            'selesai kosong setelah mulai' => [$d, '2026-03-10', null, false, $jkt('2026-03-15 12:00'), StatusSpt::Pelaksanaan],
            'selesai sebelum mulai' => [$d, '2026-03-10', '2026-03-20', true, $jkt('2026-03-09 12:00'), StatusSpt::Selesai],
            'selesai dalam rentang' => [$d, '2026-03-10', '2026-03-20', true, $jkt('2026-03-15 12:00'), StatusSpt::Selesai],
            'selesai setelah rentang' => [$d, '2026-03-10', '2026-03-20', true, $jkt('2026-03-25 12:00'), StatusSpt::Selesai],
            // 23:30 Jakarta = 16:30 UTC; 00:30 Jakarta is still the previous UTC day.
            '23.30 WIB sebelum mulai' => [$d, '2026-03-10', '2026-03-20', false, $jkt('2026-03-09 23:30'), StatusSpt::SiapDilaksanakan],
            '23.30 WIB hari selesai' => [$d, '2026-03-10', '2026-03-20', false, $jkt('2026-03-20 23:30'), StatusSpt::Pelaksanaan],
            '00.30 WIB hari mulai (UTC kemarin)' => [$d, '2026-03-10', '2026-03-20', false, '2026-03-09 17:30 UTC', StatusSpt::Pelaksanaan],
            '00.30 WIB setelah selesai (UTC kemarin)' => [$d, '2026-03-10', '2026-03-20', false, '2026-03-20 17:30 UTC', StatusSpt::PenyusunanLaporan],
        ];
        foreach ([StatusPengajuan::Draf, StatusPengajuan::MenungguNomor, StatusPengajuan::PerluRevisi, StatusPengajuan::Ditolak, StatusPengajuan::Dibatalkan] as $p) {
            foreach (['2026-03-09', '2026-03-15', '2026-03-25'] as $hari) {
                $cases["{$p->value} {$hari}"] = [$p, '2026-03-10', '2026-03-20', false, $jkt("$hari 12:00"), StatusSpt::from($p->value)];
            }
        }

        return $cases;
    }

    #[DataProvider('matriksStatus')]
    public function test_status_matrix_and_berstatus_scope(StatusPengajuan $pengajuan, ?string $mulai, ?string $selesai, bool $ditandai, string $now, StatusSpt $expected): void
    {
        $spt = Spt::factory()->diterima()->create(['tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai]);
        $spt->forceFill(['status_pengajuan' => $pengajuan, 'ditandai_selesai_pada' => $ditandai ? '2026-03-01 08:00:00' : null])->save();
        $spt = $spt->fresh();

        $this->travelTo(CarbonImmutable::parse($now));
        $this->assertSame($expected, $spt->status());
        foreach (StatusSpt::cases() as $status) {
            $this->assertSame($status === $expected ? [$spt->id] : [], Spt::berstatus($status)->pluck('id')->all(), $status->value);
        }
    }

    public function test_on_process_group(): void
    {
        $this->assertSame(
            [StatusSpt::SiapDilaksanakan, StatusSpt::Pelaksanaan, StatusSpt::PenyusunanLaporan],
            array_values(array_filter(StatusSpt::cases(), fn ($s) => $s->onProcess())),
        );
    }
}
