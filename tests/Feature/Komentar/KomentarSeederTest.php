<?php

namespace Tests\Feature\Komentar;

use App\Enums\Role;
use App\Enums\StatusPermintaan;
use App\Models\Komentar;
use App\Models\User;
use App\Support\DiinputOleh;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Fase 7 Task 4: dev demo threads. */
class KomentarSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_seeder_creates_three_threads_once_without_notifications(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotent

        $roots = Komentar::whereNull('parent_id')->get();
        $this->assertCount(3, $roots);
        $this->assertSame(1, Komentar::permintaanTerbuka()->count());
        $this->assertSame(1, $roots->where('status_permintaan', StatusPermintaan::Selesai)->count());
        $balasan = Komentar::whereNotNull('parent_id')->with('penulis')->get();
        $this->assertSame(3, $balasan->count());
        $irban = $balasan->filter(fn ($k) => $k->penulis->role->value === 'irban');
        $this->assertCount(2, $irban);
        $this->assertNotContains(null, $irban->pluck('diinput_oleh')->all());
        $this->assertSame(1, $balasan->filter(fn ($k) => $k->penulis->role->value === 'bagian_umum')->count());
        $this->assertSame(2, $roots->where('minta_penjelasan', true)->count());
        $this->assertCount(2, $roots->pluck('spt_id')->unique());
        Mail::assertNothingOutgoing();
    }

    public function test_penginput_penutup_dan_urutan_waktu(): void
    {
        $this->seed(DatabaseSeeder::class);

        $irbanI = User::where('username', 'irban1')->firstOrFail();
        $pimpinan = User::where('username', 'pimpinan')->firstOrFail();
        $anggota = DiinputOleh::pilihan($irbanI)->pluck('id')->all();
        $this->assertNotEmpty($anggota);

        $semua = Komentar::with('penulis')->get();
        $irbanKomentar = $semua->filter(fn ($k) => $k->penulis->role === Role::Irban);
        $this->assertCount(2, $irbanKomentar);
        foreach ($irbanKomentar as $k) {
            $this->assertContains($k->diinput_oleh, $anggota);
        }
        foreach ($semua->reject(fn ($k) => $k->penulis->role === Role::Irban) as $k) {
            $this->assertNull($k->diinput_oleh); // positive control: only Irban comments carry a penginput
        }

        $selesai = $semua->firstWhere('status_permintaan', StatusPermintaan::Selesai);
        $this->assertSame($pimpinan->id, $selesai->ditutup_oleh);
        $this->assertNotNull($selesai->ditutup_pada);
        $balasan = $semua->where('parent_id', $selesai->id);
        $this->assertCount(1, $balasan);
        $this->assertTrue($selesai->ditutup_pada->gt($balasan->first()->created_at));

        $terbuka = $semua->firstWhere('status_permintaan', StatusPermintaan::Terbuka);
        $this->assertNull($terbuka->ditutup_oleh);
        $this->assertNull($terbuka->ditutup_pada);
        $this->assertCount(0, $semua->where('parent_id', $terbuka->id)); // no Irban reply yet

        foreach ($semua->whereNotNull('parent_id') as $k) {
            $this->assertTrue($k->created_at->gt($semua->firstWhere('id', $k->parent_id)->created_at), "balasan {$k->id} setelah induknya");
        }
    }
}
