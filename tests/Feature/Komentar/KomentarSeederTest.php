<?php

namespace Tests\Feature\Komentar;

use App\Enums\StatusPermintaan;
use App\Models\Komentar;
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
}
