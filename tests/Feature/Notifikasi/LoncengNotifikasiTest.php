<?php

namespace Tests\Feature\Notifikasi;

use App\Enums\StatusPengajuan;
use App\Livewire\Notifikasi\Lonceng;
use App\Models\Irban;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\PengajuanBaru;
use App\Notifications\PengajuanDiputuskan;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 6 Task 5: header bell (count, dropdown, mark read, poll announcement). */
class LoncengNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private const SR_BELUM = '<span class="sr-only">Belum dibaca</span>';

    private function buat(User $user, bool $dibaca = false, string $judul = 'Judul', string $url = '/spt/1', ?string $waktu = null): DatabaseNotification
    {
        $n = $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\NomorDibatalkan',
            'data' => ['jenis' => 'nomor_dibatalkan', 'judul' => $judul, 'konteks' => 'SPT 1/2026', 'url' => $url, 'catatan' => null],
            'read_at' => $dibaca ? now() : null,
        ]);
        if ($waktu) {
            $n->forceFill(['created_at' => $waktu, 'updated_at' => $waktu])->save();
        }

        return $n;
    }

    public function test_count_and_aria_label(): void
    {
        $user = User::factory()->pimpinan()->create();
        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)
            ->assertSeeHtml('aria-label="Notifikasi, tidak ada yang belum dibaca"')->assertDontSeeHtml('data-lencana');

        foreach (range(1, 3) as $i) {
            $this->buat($user);
        }
        $this->buat($user, true);
        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)
            ->assertSeeHtml('aria-label="Notifikasi, 3 belum dibaca"')->assertSeeHtml('data-lencana>3<');

        foreach (range(1, 9) as $i) {
            $this->buat($user);
        }
        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)
            ->assertSeeHtml('aria-label="Notifikasi, 12 belum dibaca"')->assertSeeHtml('data-lencana>9+<');
    }

    public function test_dropdown_lists_at_most_eight_newest_first_with_unread_marker(): void
    {
        $user = User::factory()->pimpinan()->create();
        foreach (range(1, 10) as $i) {
            $this->buat($user, $i <= 7, "Item {$i}x", waktu: now()->subMinutes(20 - $i)->toDateTimeString());
        }
        $this->buat(User::factory()->pimpinan()->create(), false, 'Milik orang lain');

        $c = Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true);
        $items = $c->viewData('notifikasi');
        $this->assertCount(8, $items);
        $this->assertSame('Item 10x', $items->first()->data['judul']);
        $this->assertSame('Item 3x', $items->last()->data['judul']);
        $c->assertDontSee('Milik orang lain')->assertDontSee('Item 2x');
        $this->assertSame(3, substr_count($c->html(), self::SR_BELUM));
    }

    public function test_tab_belum_filters_and_empty_states(): void
    {
        $user = User::factory()->pimpinan()->create();
        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)->assertSee('Belum ada notifikasi')
            ->set('tab', 'belum')->assertSee('Semua notifikasi sudah dibaca');

        $this->buat($user, false, 'Belum A');
        $this->buat($user, true, 'Sudah B');
        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)
            ->assertSee('Belum A')->assertSee('Sudah B')->assertSee('Tandai semua dibaca')->assertSee('Lihat semua notifikasi')
            ->set('tab', 'belum')->assertSee('Belum A')->assertDontSee('Sudah B')
            ->set('tab', 'aneh')->assertSee('Sudah B');
    }

    public function test_buka_marks_own_and_redirects_ignores_others(): void
    {
        $user = User::factory()->irban()->create();
        $milik = $this->buat($user, url: '/spt/42');
        $lain = $this->buat(User::factory()->irban()->create(), url: '/spt/99');

        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)->call('buka', $lain->id)->assertNoRedirect();
        $this->assertNull($lain->fresh()->read_at);

        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)->call('buka', $milik->id)->assertRedirect('/spt/42');
        $this->assertNotNull($milik->fresh()->read_at);
    }

    public function test_tandai_semua_dibaca_affects_only_current_user(): void
    {
        $user = User::factory()->bagianUmum()->create();
        $a = $this->buat($user);
        $b = $this->buat($user);
        $lain = $this->buat(User::factory()->bagianUmum()->create());

        Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)->call('tandaiSemuaDibaca')
            ->assertSeeHtml('aria-label="Notifikasi, tidak ada yang belum dibaca"')->assertDontSee('Tandai semua dibaca');
        $this->assertNotNull($a->fresh()->read_at);
        $this->assertNotNull($b->fresh()->read_at);
        $this->assertNull($lain->fresh()->read_at);
    }

    public function test_list_is_only_queried_while_open(): void
    {
        $user = User::factory()->pimpinan()->create();
        $this->buat($user, false, 'Terlihat');
        $c = Livewire::actingAs($user)->test(Lonceng::class);
        $this->assertCount(0, $c->viewData('notifikasi'));
        $c->assertDontSee('Terlihat')->assertSeeHtml('aria-label="Notifikasi, 1 belum dibaca"');
        $c->set('terbuka', true)->assertSee('Terlihat');
    }

    public function test_mark_all_dispatches_event_and_list_event_refreshes_count_without_ringing(): void
    {
        $user = User::factory()->pimpinan()->create();
        $this->buat($user);
        Livewire::actingAs($user)->test(Lonceng::class)->call('tandaiSemuaDibaca')->assertDispatched('lonceng-diperbarui');

        $c = Livewire::actingAs($user)->test(Lonceng::class);
        $user->unreadNotifications()->update(['read_at' => now()]); // marked elsewhere (the list page)
        $c->dispatch('notifikasi-diperbarui')->assertSeeHtml('aria-label="Notifikasi, tidak ada yang belum dibaca"')
            ->assertNotDispatched('lonceng-dering')->assertDontSee('notifikasi baru');
    }

    public function test_renders_on_every_role_dashboard(): void
    {
        foreach (['admin', 'pimpinan', 'bagianUmum', 'irban'] as $role) {
            $user = User::factory()->{$role}()->create($role === 'irban' ? ['irban_id' => Irban::factory()->create()->id] : []);
            $this->actingAs($user)->get(route($user->role->dashboardRoute()))
                ->assertOk()->assertSeeLivewire(Lonceng::class)->assertSee('Notifikasi, tidak ada yang belum dibaca');
        }
    }

    public function test_poll_announces_new_notifications_only_when_closed(): void
    {
        $user = User::factory()->pimpinan()->create();
        $this->buat($user);
        $c = Livewire::actingAs($user)->test(Lonceng::class)->set('terbuka', true)->assertDontSee('notifikasi baru');

        $this->buat($user);
        $c->call('periksa', true)->assertDontSee('notifikasi baru')->assertNotDispatched('lonceng-dering');
        $c->call('periksa', false)->assertSee('1 notifikasi baru')->assertDispatched('lonceng-dering')
            ->assertSeeHtml('aria-label="Notifikasi, 2 belum dibaca"');
        $c->call('periksa', false)->assertDontSee('notifikasi baru');
    }

    public function test_konteks_without_jenis_penugasan_has_no_leading_separator(): void
    {
        $spt = Spt::factory()->create(['jenis_penugasan_id' => null, 'uraian_untuk' => 'Pemeriksaan khusus dana desa.']);
        $user = User::factory()->pimpinan()->create();

        $this->assertSame('Pemeriksaan khusus dana desa.', (new PengajuanBaru($spt))->toArray($user)['konteks']);
        $spt->status_pengajuan = StatusPengajuan::PerluRevisi;
        $this->assertSame('Pemeriksaan khusus dana desa.', (new PengajuanDiputuskan($spt))->toArray($user)['konteks']);
    }

    public function test_dev_seeder_gives_demo_users_read_and_unread_notifications_once(): void
    {
        Mail::fake();
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class); // idempotent

        $jumlah = [];
        foreach (['irban1', 'bagianumum', 'pimpinan'] as $username) {
            $user = User::where('username', $username)->first();
            $this->assertGreaterThan(0, $user->unreadNotifications()->count(), $username);
            $user->notifications->each(fn ($n) => $this->assertSame(['jenis', 'judul', 'konteks', 'url', 'catatan'], array_keys($n->data)));
            $jumlah[$username] = $user->notifications->pluck('data.jenis')->unique()->sort()->values()->all();
        }
        $this->assertGreaterThan(0, User::where('username', 'irban1')->first()->readNotifications()->count());
        $this->assertSame(['nomor_dibatalkan', 'pengajuan_diterima', 'pengajuan_perlu_revisi', 'revisi_jadwal_disetujui', 'spt_terlambat'], $jumlah['irban1']);
        $this->assertSame(['pengajuan_baru'], $jumlah['bagianumum']);
        $this->assertSame(['revisi_jadwal_diajukan', 'ringkasan_mingguan'], $jumlah['pimpinan']);
        Mail::assertNothingOutgoing();
    }
}
