<?php

namespace Tests\Feature\Log;

use App\Enums\Role;
use App\Livewire\Admin\Dasbor;
use App\Livewire\LogAktivitas;
use App\Models\Aktivitas;
use App\Models\Irban;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\SptKendala;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HalamanLogAktivitasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function baris(array $a = []): Aktivitas
    {
        return Aktivitas::create($a + ['log_name' => 'default', 'description' => 'x', 'event' => 'created', 'properties' => []]);
    }

    private function user(Role $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /** Fixture setup (users, SPT) logs rows itself; tests that count rows start from an empty log. */
    private function bersih(): void
    {
        DB::table('activity_log')->delete();
    }

    private function causer(User $u): array
    {
        return ['causer_type' => $u->getMorphClass(), 'causer_id' => $u->id];
    }

    public function test_diff_pivot_tampil_sebagai_daftar_nama(): void
    {
        $this->baris(['event' => 'updated', 'properties' => ['old' => ['objek' => ['Objek A']], 'attributes' => ['objek' => ['Objek A', 'Objek B'], 'anggota' => []]]]);

        Livewire::actingAs($this->user(Role::Admin))->test(LogAktivitas::class)->assertSee('Objek A, Objek B')->assertDontSee('["Objek A"');
    }

    public function test_akses(): void
    {
        $admin = User::factory()->admin()->create();
        $pimpinan = $this->user(Role::Pimpinan);
        $this->actingAs($admin)->get('/admin/log')->assertOk()->assertSeeLivewire(LogAktivitas::class);
        $this->actingAs($pimpinan)->get('/pimpinan/log')->assertOk();
        foreach ([Role::Irban, Role::BagianUmum] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->get('/admin/log')->assertForbidden();
            $this->actingAs($u)->get('/pimpinan/log')->assertForbidden();
            Livewire::actingAs($u)->test(LogAktivitas::class)->assertForbidden();
        }
        $this->actingAs($admin)->get('/pimpinan/log')->assertForbidden();
    }

    public function test_filter(): void
    {
        $a = $this->user(Role::Irban);
        $b = $this->user(Role::Irban);
        $admin = $this->user(Role::Admin);
        $this->bersih();
        $this->baris(['subject_type' => (new Pegawai)->getMorphClass(), 'subject_id' => 1, 'event' => 'created', 'created_at' => '2026-03-10 08:00:00'] + $this->causer($a));
        $this->baris(['subject_type' => (new Spt)->getMorphClass(), 'subject_id' => 7, 'event' => 'deleted', 'created_at' => '2026-04-10 08:00:00'] + $this->causer($b));
        $this->baris(['log_name' => 'pengaturan', 'event' => 'updated', 'created_at' => '2026-05-10 08:00:00'] + $this->causer($b));
        $this->baris(['log_name' => 'impor', 'event' => 'impor', 'properties' => ['jenis' => 'spt', 'berkas' => 'BERKAS-X.xlsx', 'jumlah' => 3], 'created_at' => '2026-05-11 08:00:00']);
        $this->baris(['subject_type' => (new SptKendala)->getMorphClass(), 'subject_id' => 3, 'event' => 'deleted', 'properties' => ['old' => ['spt_id' => 7]], 'created_at' => '2026-05-12 08:00:00']);

        $n = fn (array $f) => Livewire::actingAs($admin)->withQueryParams($f)->test(LogAktivitas::class)->viewData('baris')->total();
        $this->assertSame(5, $n([]));
        $this->assertSame(1, $n(['pengguna' => $a->id]));
        $this->assertSame(2, $n(['pengguna' => $b->id]));
        $this->assertSame(1, $n(['objek' => 'pegawai']));
        $this->assertSame(1, $n(['objek' => 'pengaturan']));
        $this->assertSame(2, $n(['aksi' => 'deleted']));
        $this->assertSame(1, $n(['aksi' => 'lainnya']));
        $this->assertSame(3, $n(['dari' => '2026-05-01']));
        $this->assertSame(2, $n(['sampai' => '2026-04-30']));
        $this->assertSame(2, $n(['dari' => '2026-04-01', 'sampai' => '2026-05-10']));
        $this->assertSame(2, $n(['spt' => 7]));
        $this->assertSame(5, $n(['pengguna' => 'abc', 'dari' => 'bukan-tanggal', 'aksi' => 'zzz', 'objek' => 'zzz']));
    }

    public function test_paginasi_25_terbaru_dulu(): void
    {
        $pimpinan = $this->user(Role::Pimpinan);
        $this->bersih();
        for ($i = 1; $i <= 30; $i++) {
            $this->baris();
        }
        $baris = Livewire::actingAs($pimpinan)->test(LogAktivitas::class)->viewData('baris');
        $this->assertCount(25, $baris);
        $ids = $baris->pluck('id')->all();
        $this->assertSame(range($ids[0], $ids[0] - 24), $ids);
        $this->assertSame(Aktivitas::max('id'), $ids[0]);
    }

    public function test_diff_ditampilkan(): void
    {
        $penginput = Pegawai::factory()->create(['nama_bergelar' => 'Penginput Uji']);
        $u = $this->user(Role::Irban);
        $admin = $this->user(Role::Admin);
        $this->bersih();
        $this->baris(['event' => 'updated', 'diinput_oleh' => $penginput->id,
            'properties' => ['old' => ['uraian' => 'LAMA-XYZ', 'meta' => null], 'attributes' => ['uraian' => 'BARU-XYZ', 'meta' => ['k' => 1]]]] + $this->causer($u));
        $this->baris(['event' => 'created', 'properties' => ['attributes' => ['x' => 'y']]]);

        $html = Livewire::actingAs($admin)->test(LogAktivitas::class)->html();
        foreach (['LAMA-XYZ', 'BARU-XYZ', '{&quot;k&quot;:1}', 'Sistem', 'Penginput Uji', $u->name, 'aria-expanded', 'aria-controls'] as $s) {
            $this->assertStringContainsString($s, $html);
        }
        $this->assertStringContainsString('—', $html);
    }

    public function test_tanpa_kontrol_hapus(): void
    {
        $admin = $this->user(Role::Admin);
        $this->baris();
        $html = $this->actingAs($admin)->get('/admin/log')->getContent();
        $this->assertDoesNotMatchRegularExpression('/wire:click="[^"]*(hapus|delete)/i', $html);
        $this->assertStringNotContainsString('Hapus', str_replace('Hapus filter', '', $html));
    }

    public function test_kosong(): void
    {
        $admin = $this->user(Role::Admin);
        $this->bersih();
        $t = Livewire::actingAs($admin)->test(LogAktivitas::class)->assertSee('Belum ada aktivitas.');
        $t->set('aksi', 'deleted')->assertSee('Tidak ada aktivitas yang cocok dengan filter.');
    }

    public function test_kartu_log_di_detail_spt(): void
    {
        $irban = Irban::factory()->create();
        $irban1 = $this->user(Role::Irban);
        $irban1->forceFill(['irban_id' => $irban->id])->save();
        $pimpinan = $this->user(Role::Pimpinan);
        $spt = Spt::factory()->diterima()->create(['irban_id' => $irban->id]);
        $this->bersih();
        $this->baris(['subject_type' => (new Spt)->getMorphClass(), 'subject_id' => $spt->id, 'event' => 'updated']);

        $this->actingAs($pimpinan)->get(route('spt.show', $spt))->assertOk()->assertSee('Log Aktivitas')->assertSee('Diubah · SPT')->assertSee("spt={$spt->id}", false);
        $this->actingAs($irban1)->get(route('spt.show', $spt))->assertOk()->assertDontSee('Log Aktivitas');
    }

    public function test_dasbor_admin_aktivitas_terbaru(): void
    {
        $admin = $this->user(Role::Admin);
        $this->bersih();
        for ($i = 1; $i <= 12; $i++) {
            $this->baris(['event' => 'updated', 'properties' => ['attributes' => ['k' => "NILAI-{$i}-Z"]]]);
        }
        $t = Livewire::actingAs($admin)->test(Dasbor::class)->assertSee('Aktivitas terbaru')->assertSee('Lihat semua');
        $this->assertCount(10, $t->viewData('aktivitas'));
        $this->assertSame(Aktivitas::max('id'), $t->viewData('aktivitas')->first()->id);
        $this->assertStringContainsString(route('admin.log'), $t->html());
    }

    public function test_jumlah_query_tetap(): void
    {
        $admin = $this->user(Role::Admin);
        $u = $this->user(Role::Irban);
        $this->bersih();
        $hitung = function () use ($admin) {
            DB::enableQueryLog();
            Livewire::actingAs($admin)->test(LogAktivitas::class);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();
            DB::flushQueryLog();

            return $n;
        };
        $this->baris(['subject_type' => (new Spt)->getMorphClass(), 'subject_id' => 1, 'diinput_oleh' => Pegawai::factory()->create()->id] + $this->causer($u));
        $sedikit = $hitung();
        for ($i = 0; $i < 20; $i++) {
            $this->baris(['subject_type' => (new Spt)->getMorphClass(), 'subject_id' => 1, 'diinput_oleh' => Pegawai::factory()->create()->id] + $this->causer($u));
        }
        $this->assertSame($sedikit, $hitung());
    }

    public function test_navigasi(): void
    {
        $this->actingAs($this->user(Role::Pimpinan))->get('/pimpinan')->assertSee('Log Aktivitas')->assertSee(route('pimpinan.log'), false);
        $this->actingAs($this->user(Role::Admin))->get('/admin')->assertSee(route('admin.log'), false);
    }
}
