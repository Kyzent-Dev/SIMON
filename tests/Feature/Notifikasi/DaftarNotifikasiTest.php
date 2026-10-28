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

    public function test_mark_actions_dispatch_event_for_the_bell_and_list_refreshes_on_bell_event(): void
    {
        $user = User::factory()->pimpinan()->create();
        $a = $this->buat($user);
        $this->buat($user);

        $c = Livewire::actingAs($user)->test(Daftar::class)->call('tandaiDibaca', $a->id)->assertDispatched('notifikasi-diperbarui');
        $c->call('tandaiSemua')->assertDispatched('notifikasi-diperbarui');

        $nBaru = $this->buat($user);
        $c->dispatch('lonceng-diperbarui')->assertSee($nBaru->data['judul']);
        $c->set('terpilih', [$nBaru->id])->call('tandaiTerpilih')->assertDispatched('notifikasi-diperbarui');
    }

    public function test_second_page_holds_the_remainder_in_newest_first_order(): void
    {
        $user = User::factory()->pimpinan()->create();
        foreach (range(1, 25) as $i) {
            $this->buat($user, true, "Item nomor {$i}x")->forceFill(['created_at' => now()->subMinutes(30 - $i)])->save();
        }

        $c = Livewire::actingAs($user)->test(Daftar::class)->call('gotoPage', 2);

        $this->assertSame(2, $c->viewData('notifikasi')->currentPage());
        $this->assertSame(['Item nomor 5x', 'Item nomor 4x', 'Item nomor 3x', 'Item nomor 2x', 'Item nomor 1x'], collect($c->viewData('notifikasi')->items())->pluck('data.judul')->all());
    }

    public function test_changing_page_empties_the_selection_and_selecting_keeps_the_page(): void
    {
        $user = User::factory()->pimpinan()->create();
        foreach (range(1, 25) as $i) {
            $this->buat($user, true, "Item nomor {$i}x");
        }
        $id = $user->notifications()->latest()->latest('id')->first()->id;

        $c = Livewire::actingAs($user)->test(Daftar::class)->set('terpilih', [$id])->assertSet('terpilih', [$id]);
        $c->call('gotoPage', 2)->assertSet('terpilih', []);

        // Selecting on page 2 (whole array, or a single index) must not throw the user back to page 1.
        $c->set('terpilih', [$id])->assertSet('paginators.page', 2);
        $c->set('terpilih.0', $id)->assertSet('paginators.page', 2);
        // Control: a filter change does reset it.
        $c->set('tab', 'belum')->assertSet('paginators.page', 1);
    }

    public function test_per_page_is_clamped_to_the_offered_sizes_and_resets_the_page(): void
    {
        $user = User::factory()->pimpinan()->create();
        foreach (range(1, 60) as $i) {
            $this->buat($user, true, "Item nomor {$i}x");
        }

        $c = Livewire::actingAs($user)->test(Daftar::class)->call('gotoPage', 2);
        $c->set('perPage', 50)->assertSet('perPage', 50)->assertSet('paginators.page', 1);
        $this->assertCount(50, $c->viewData('notifikasi')->items());
        foreach ([7, 0, -5, 100000] as $nilai) {
            $c->set('perPage', $nilai)->assertSet('perPage', 20);
        }
    }

    public function test_item_link_lets_modified_clicks_fall_through_to_the_browser(): void
    {
        $user = User::factory()->pimpinan()->create();
        $n = $this->buat($user, false, 'Judul', '/spt/9');

        $html = Livewire::actingAs($user)->test(Daftar::class)->html();

        $this->assertStringContainsString('href="/spt/9"', $html); // new tab / copy link works without JS
        // Only the meaningful parts, not the whole attribute string: the guard for modified clicks, preventDefault, and the Livewire call.
        $this->assertMatchesRegularExpression('/x-on:click="[^"]*\$event\.ctrlKey[^"]*\$event\.metaKey[^"]*\$event\.shiftKey[^"]*"/', $html);
        $this->assertMatchesRegularExpression('/x-on:click="[^"]*\$event\.preventDefault\(\)[^"]*"/', $html);
        $this->assertStringContainsString("\$wire.buka('{$n->id}')", $html);
    }

    public function test_spt_number_in_konteks_wraps_only_after_slashes_and_stays_escaped(): void
    {
        $user = User::factory()->pimpinan()->create();
        $n = $this->buat($user);
        $n->forceFill(['data' => ['jenis' => 'nomor_dibatalkan', 'judul' => 'Judul', 'konteks' => 'SPT 700/1/INSPEKTORAT-I/SPT/2026 <i>x', 'url' => '/spt/1', 'catatan' => null]])->save();

        $html = Livewire::actingAs($user)->test(Daftar::class)->html();

        $this->assertStringContainsString('SPT <span class="whitespace-nowrap">700</span>/<wbr><span class="whitespace-nowrap">1</span>/<wbr><span class="whitespace-nowrap">INSPEKTORAT-I</span>/<wbr><span class="whitespace-nowrap">SPT</span>/<wbr><span class="whitespace-nowrap">2026</span> &lt;i&gt;x', $html);
        $this->assertStringNotContainsString('<i>x', $html);
    }
}
