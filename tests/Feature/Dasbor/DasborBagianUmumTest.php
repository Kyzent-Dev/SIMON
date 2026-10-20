<?php

namespace Tests\Feature\Dasbor;

use App\Enums\KejadianSpt;
use App\Enums\Role;
use App\Livewire\BagianUmum\Dasbor;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Dasbor Bagian Umum (Fase 8, Task 6; bagian-umum.md "Dashboard (minimal)"). Today 15 Juni 2026 (Jakarta). */
class DasborBagianUmumTest extends TestCase
{
    use RefreshDatabase;

    private User $bu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-06-15 10:00', 'Asia/Jakarta'));
        $this->bu = User::factory()->bagianUmum()->create();
    }

    public function test_route_is_the_livewire_page_and_other_roles_are_forbidden(): void
    {
        $this->actingAs($this->bu)->get('/bagian-umum')->assertOk()->assertSeeLivewire(Dasbor::class)->assertSee('Dasbor Bagian Umum');
        foreach ([Role::Pimpinan, Role::Irban, Role::Admin] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $this->actingAs($u)->get('/bagian-umum')->assertForbidden();
            Livewire::actingAs($u)->test(Dasbor::class)->assertForbidden();
        }
    }

    public function test_nomor_terbit_bulan_ini_counts_issued_and_manual_numbers_inside_the_jakarta_month(): void
    {
        $spt = Spt::factory()->diterima()->create();
        $riwayat = fn (string $kejadian, string $waktu) => $spt->riwayat()->create(['kejadian' => $kejadian, 'oleh' => $this->bu->id])
            ->forceFill(['created_at' => $waktu])->save();
        $riwayat(KejadianSpt::DITERIMA, '2026-05-31 23:59:59');      // last second of May: no
        $riwayat(KejadianSpt::DITERIMA, '2026-06-01 00:00:00');      // first second of June: yes
        $riwayat(KejadianSpt::DICATAT_MANUAL, '2026-06-10 12:00:00'); // manual number: yes
        $riwayat(KejadianSpt::DITERIMA, '2026-06-30 23:59:59');      // last second of June: yes
        $riwayat(KejadianSpt::DITERIMA, '2026-07-01 00:00:00');      // July: no
        $riwayat(KejadianSpt::PERLU_REVISI, '2026-06-10 12:00:00');  // not a number: no
        $riwayat(KejadianSpt::DIBATALKAN, '2026-06-11 12:00:00');    // not a number: no

        $kartu = collect(Livewire::actingAs($this->bu)->test(Dasbor::class)->viewData('kartu'))->keyBy('label');
        $this->assertSame(3, $kartu['Nomor Terbit Bulan Ini']['nilai']);
        $this->assertNull($kartu['Nomor Terbit Bulan Ini']['href']);
    }

    public function test_menunggu_keputusan_and_the_five_oldest_requests(): void
    {
        $irban = Irban::factory()->create(['nama' => 'Irban II']);
        $ids = [];
        // diajukan 1..6 Juni; the 6 Juni one is the newest and falls off the list of five.
        foreach ([3, 1, 6, 2, 5, 4] as $hari) {
            $ids[$hari] = Spt::factory()->menungguNomor()->create(['irban_id' => $irban->id, 'diajukan_pada' => "2026-06-0{$hari} 09:00"])->id;
        }
        Spt::factory()->perluRevisi()->create(['diajukan_pada' => '2026-05-01 09:00']);
        Spt::factory()->diterima()->create();

        $h = Livewire::actingAs($this->bu)->test(Dasbor::class);
        $kartu = collect($h->viewData('kartu'))->keyBy('label');
        $this->assertSame(['Menunggu Keputusan', 'Nomor Terbit Bulan Ini'], $kartu->keys()->all());
        $this->assertSame(6, $kartu['Menunggu Keputusan']['nilai']);
        $this->assertSame(route('bagian-umum.pengajuan'), $kartu['Menunggu Keputusan']['href']);

        $terlama = $h->viewData('terlama');
        $this->assertSame([$ids[1], $ids[2], $ids[3], $ids[4], $ids[5]], $terlama->pluck('id')->all());
        // 1 Juni 09:00 → 15 Juni 10:00 = 14 full days.
        $h->assertSee('14 hari')->assertSee('Irban II')
            ->assertSee(route('bagian-umum.pengajuan', ['pilih' => $ids[1]]), false)
            ->assertDontSee(route('bagian-umum.pengajuan', ['pilih' => $ids[6]]), false);
    }
}
