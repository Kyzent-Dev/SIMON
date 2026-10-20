<?php

namespace Tests\Feature\Notifikasi;

use App\Livewire\Notifikasi\Daftar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/** Fase 6 Task 4: notification list page (own notifications only, tabs, bulk + per-item read). */
class DaftarNotifikasiTest extends TestCase
{
    use RefreshDatabase;

    private function buat(User $user, bool $dibaca = false, string $judul = 'Judul', string $url = '/spt/1'): DatabaseNotification
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\Notifications\NomorDibatalkan',
            'data' => ['jenis' => 'nomor_dibatalkan', 'judul' => $judul, 'konteks' => 'SPT 1/2026', 'url' => $url, 'catatan' => null],
            'read_at' => $dibaca ? now() : null,
        ]);
    }

    public function test_guest_is_redirected_and_every_role_gets_200(): void
    {
        $this->get(route('notifikasi.index'))->assertRedirect(route('login'));

        foreach (['admin', 'pimpinan', 'bagianUmum', 'irban'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())->get(route('notifikasi.index'))
                ->assertOk()->assertSee('Notifikasi')->assertSee('Semua pemberitahuan untuk akun Anda.');
        }
    }

    public function test_lists_only_own_notifications_and_tabs_filter(): void
    {
        $user = User::factory()->pimpinan()->create();
        $this->buat($user, false, 'Belum A');
        $this->buat($user, true, 'Sudah B');
        $this->buat(User::factory()->pimpinan()->create(), false, 'Milik orang lain');

        Livewire::actingAs($user)->test(Daftar::class)
            ->assertSee('Belum A')->assertSee('Sudah B')->assertDontSee('Milik orang lain')
            ->set('tab', 'belum')->assertSee('Belum A')->assertDontSee('Sudah B');
    }

    public function test_paginates_twenty_per_page(): void
    {
        $user = User::factory()->pimpinan()->create();
        foreach (range(1, 25) as $i) {
            $this->buat($user, true, "Item nomor {$i}x");
        }

        $c = Livewire::actingAs($user)->test(Daftar::class);
        $this->assertCount(20, $c->viewData('notifikasi')->items());
        $this->assertSame(25, $c->viewData('notifikasi')->total());
    }

    public function test_mark_single_and_bulk_ignore_other_users_ids(): void
    {
        $user = User::factory()->pimpinan()->create();
        $lain = $this->buat(User::factory()->pimpinan()->create());
        $a = $this->buat($user);
        $b = $this->buat($user);
        $c = $this->buat($user);

        $comp = Livewire::actingAs($user)->test(Daftar::class)->call('tandaiDibaca', $a->id);
        $this->assertNotNull($a->fresh()->read_at);
        $comp->call('tandaiDibaca', $lain->id);
        $this->assertNull($lain->fresh()->read_at);
        $comp->set('terpilih', [$b->id, $lain->id])->call('tandaiTerpilih')
            ->assertDispatched('toast', type: 'success', message: '1 notifikasi ditandai dibaca');
        $this->assertNotNull($b->fresh()->read_at);
        $this->assertNull($lain->fresh()->read_at);
        $this->assertNull($c->fresh()->read_at);
    }

    public function test_item_click_marks_read_and_redirects_to_url_only_for_own(): void
    {
        $user = User::factory()->irban()->create();
        $n = $this->buat($user, false, 'X', '/spt/9');
        $lain = $this->buat(User::factory()->irban()->create(), false, 'Y', '/spt/8');

        Livewire::actingAs($user)->test(Daftar::class)->call('buka', $n->id)->assertRedirect('/spt/9');
        $this->assertNotNull($n->fresh()->read_at);

        Livewire::actingAs($user)->test(Daftar::class)->call('buka', $lain->id)->assertNoRedirect();
        $this->assertNull($lain->fresh()->read_at);
    }

    public function test_mark_all_affects_only_current_user_and_toasts_count(): void
    {
        $user = User::factory()->bagianUmum()->create();
        $lain = $this->buat(User::factory()->bagianUmum()->create());
        $this->buat($user);
        $this->buat($user);
        $this->buat($user, true);

        Livewire::actingAs($user)->test(Daftar::class)->call('tandaiSemua')
            ->assertDispatched('toast', type: 'success', message: '2 notifikasi ditandai dibaca');
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertNull($lain->fresh()->read_at);
    }

    public function test_bulk_action_emptying_unread_view_moves_focus_to_heading(): void
    {
        $user = User::factory()->bagianUmum()->create();
        $this->buat($user);

        Livewire::actingAs($user)->test(Daftar::class)->set('tab', 'belum')->call('tandaiSemua')
            ->assertDispatched('fokus-ke', id: 'judul-notifikasi')
            ->assertSee('Semua notifikasi sudah dibaca');
    }

    public function test_empty_states(): void
    {
        $user = User::factory()->admin()->create();

        Livewire::actingAs($user)->test(Daftar::class)->assertSee('Belum ada notifikasi')
            ->set('tab', 'belum')->assertSee('Semua notifikasi sudah dibaca')->assertSee('Lihat semua');
    }

    public function test_unknown_tab_falls_back_to_semua(): void
    {
        $user = User::factory()->admin()->create();
        $this->buat($user, true, 'Sudah C');

        Livewire::actingAs($user)->test(Daftar::class)->set('tab', 'aneh')->assertSee('Sudah C');
    }

    public function test_marking_moves_focus_to_a_stable_target_on_both_tabs(): void
    {
        $user = User::factory()->bagianUmum()->create();
        $a = $this->buat($user);
        $this->buat($user);

        $c = Livewire::actingAs($user)->test(Daftar::class)->call('tandaiDibaca', $a->id)
            ->assertDispatched('fokus-ke', id: 'notif-'.$a->id);
        $c->call('tandaiSemua')->assertDispatched('fokus-ke', id: 'judul-notifikasi');
        $c->set('terpilih', [$a->id])->call('tandaiTerpilih')->assertDispatched('fokus-ke', id: 'judul-notifikasi');

        $b = $this->buat($user);
        Livewire::actingAs($user)->test(Daftar::class)->set('tab', 'belum')->call('tandaiDibaca', $b->id)
            ->assertDispatched('fokus-ke', id: 'judul-notifikasi');
    }
}
