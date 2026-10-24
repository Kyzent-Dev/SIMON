<?php

namespace Tests\Feature\Pkpt;

use App\Actions\Pkpt\Perubahan\AjukanPerubahan;
use App\Actions\Pkpt\Perubahan\AturPengingatSk;
use App\Actions\Pkpt\Perubahan\BuangPerubahan;
use App\Actions\Pkpt\Perubahan\BuatPerubahan;
use App\Actions\Pkpt\Perubahan\HapusOperasi;
use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use App\Actions\Pkpt\Perubahan\SimpanOperasi;
use App\Actions\Pkpt\Perubahan\SimpanSkPerubahan;
use App\Models\Irban;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\PkptPerubahanItem;
use App\Models\User;
use Database\Factories\PkptPerubahanItemFactory;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fase 9.2 Task 6: who may do what with a Perubahan PKPT, and who may read its SK. */
class PerubahanPkptOtorisasiTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private PkptItem $item;

    private PkptPerubahan $p;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->irban = Irban::factory()->create();
        $this->item = PkptItem::factory()->create(['irban_id' => $this->irban->id]);
        $this->p = PkptPerubahan::factory()->create(['pkpt_id' => $this->item->pkpt_id]);
    }

    /** @return array<string, User> */
    private function pengguna(): array
    {
        return [
            'admin' => User::factory()->admin()->create(),
            'pimpinan' => User::factory()->pimpinan()->create(),
            'irban' => User::factory()->irban()->create(['irban_id' => $this->irban->id]),
            'irban lain' => User::factory()->irban()->create(['irban_id' => Irban::factory()->create()->id]),
            'bagian umum' => User::factory()->bagianUmum()->create(),
        ];
    }

    private function ditolak(callable $fn, string $peran, string $aksi): void
    {
        try {
            $fn();
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);

            return;
        }
        $this->fail("{$peran} seharusnya tidak boleh {$aksi}.");
    }

    public function test_only_admin_prepares_and_only_pimpinan_decides(): void
    {
        $op = PkptPerubahanItem::factory()->create(['perubahan_id' => $this->p->id, 'pkpt_item_id' => $this->item->id]);
        $aksi = [
            'buat' => fn (User $u) => app(BuatPerubahan::class)->handle($u, $this->p->pkpt, ['jenis' => 'perubahan', 'alasan' => 'x']),
            'perbarui' => fn (User $u) => app(BuatPerubahan::class)->perbarui($u, $this->p, ['jenis' => 'perubahan', 'alasan' => 'x']),
            'simpan operasi' => fn (User $u) => app(SimpanOperasi::class)->handle($u, $this->p, null, ['aksi' => 'hapus', 'pkpt_item_id' => $this->item->id]),
            'hapus operasi' => fn (User $u) => app(HapusOperasi::class)->handle($u, $op),
            'ajukan' => fn (User $u) => app(AjukanPerubahan::class)->handle($u, $this->p),
            'buang' => fn (User $u) => app(BuangPerubahan::class)->handle($u, $this->p),
            'simpan SK' => fn (User $u) => app(SimpanSkPerubahan::class)->handle($u, $this->p, ['nomor_sk' => 'SK'], null),
            'atur pengingat' => fn (User $u) => app(AturPengingatSk::class)->handle($u, $this->p, false),
            'putuskan' => fn (User $u) => app(PutuskanPerubahan::class)->handle($u, $this->p, false, 'Tidak.'),
        ];
        foreach ($this->pengguna() as $peran => $u) {
            foreach ($aksi as $nama => $fn) {
                $boleh = $nama === 'putuskan' ? $peran === 'pimpinan' : $peran === 'admin';
                if (! $boleh) {
                    $this->ditolak(fn () => $fn($u), $peran, $nama);
                }
            }
        }
        $this->assertSame('draf', $this->p->fresh()->status->value);

        // The allowed roles get past authorization (a status rule may still stop them afterwards).
        $admin = User::factory()->admin()->create();
        app(SimpanSkPerubahan::class)->handle($admin, $this->p, ['nomor_sk' => 'SK/1'], null);
        $this->assertSame('SK/1', $this->p->fresh()->nomor_sk);
        $this->p->forceFill(['status' => 'diajukan'])->save();
        app(PutuskanPerubahan::class)->handle(User::factory()->pimpinan()->create(), $this->p, false, 'Tidak.');
        $this->assertSame('ditolak', $this->p->fresh()->status->value);
    }

    public function test_irban_views_only_packages_touching_its_irban(): void
    {
        $u = $this->pengguna();
        $this->assertTrue($u['admin']->can('view', $this->p));
        $this->assertTrue($u['pimpinan']->can('view', $this->p));
        $this->assertTrue($u['admin']->can('viewAny', PkptPerubahan::class));
        $this->assertFalse($u['irban']->can('viewAny', PkptPerubahan::class));
        $this->assertFalse($u['bagian umum']->can('view', $this->p));
        $this->assertFalse($u['irban']->can('view', $this->p)); // no operation yet

        // Through the item's irban (hapus), through data_baru (tambah elsewhere), through data_lama (moved away after approval).
        $op = PkptPerubahanItem::factory()->create(['perubahan_id' => $this->p->id, 'aksi' => 'hapus', 'pkpt_item_id' => $this->item->id, 'data_baru' => []]);
        $this->assertTrue($u['irban']->can('view', $this->p));
        $this->assertFalse($u['irban lain']->can('view', $this->p));
        $op->forceFill(['aksi' => 'tambah', 'pkpt_item_id' => null, 'data_baru' => PkptPerubahanItemFactory::data($this->item)])->save();
        $this->assertTrue($u['irban']->can('view', $this->p));
        $lain = PkptItem::factory()->create(['pkpt_id' => $this->item->pkpt_id, 'irban_id' => $u['irban lain']->irban_id]);
        $op->forceFill(['aksi' => 'ubah', 'pkpt_item_id' => $lain->id, 'data_baru' => PkptPerubahanItemFactory::data($lain),
            'data_lama' => PkptPerubahanItemFactory::data($this->item)])->save();
        $this->assertTrue($u['irban']->can('view', $this->p));
        $this->assertTrue($u['irban lain']->can('view', $this->p));
    }

    public function test_sk_download_route(): void
    {
        Storage::fake('local');
        PkptPerubahanItem::factory()->create(['perubahan_id' => $this->p->id, 'aksi' => 'hapus', 'pkpt_item_id' => $this->item->id, 'data_baru' => []]);
        $u = $this->pengguna();
        $url = route('perubahan-pkpt.sk', $this->p);

        $this->actingAs($u['admin'])->get($url)->assertNotFound(); // no file yet

        $this->p->forceFill(['berkas_sk' => "perubahan-pkpt/{$this->p->id}/x.pdf"])->save();
        $this->actingAs($u['admin'])->get($url)->assertNotFound(); // row points to a missing file
        Storage::disk('local')->put($this->p->berkas_sk, '%PDF-1.4 SK');

        $this->p->forceFill(['status' => 'diajukan'])->save();
        $this->actingAs($u['irban'])->get($url)->assertForbidden(); // not approved yet
        $this->assertFalse($u['irban']->can('unduhSk', $this->p));
        $this->p->forceFill(['status' => 'disetujui'])->save();

        foreach (['admin', 'pimpinan', 'irban'] as $peran) {
            $this->actingAs($u[$peran])->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        }
        $this->assertStringContainsString('attachment', $this->actingAs($u['admin'])->get($url.'?unduh=1')->headers->get('Content-Disposition'));
        $this->assertStringContainsString('inline', $this->actingAs($u['admin'])->get($url)->headers->get('Content-Disposition'));
        foreach (['irban lain', 'bagian umum'] as $peran) {
            $this->actingAs($u[$peran])->get($url)->assertForbidden();
        }
        $this->assertTrue($u['irban']->can('unduhSk', $this->p));
    }

    public function test_sk_is_never_reachable_from_public_routes(): void
    {
        auth()->logout();
        $this->p->forceFill(['berkas_sk' => 'perubahan-pkpt/1/x.pdf', 'nomor_sk' => 'SK-RAHASIA-PERUBAHAN'])->save();
        $this->get(route('perubahan-pkpt.sk', $this->p))->assertRedirect(route('login'));
        $this->assertContains('auth', Route::getRoutes()->getByName('perubahan-pkpt.sk')->gatherMiddleware());
        foreach (['beranda', 'publik.jadwal', 'publik.realisasi'] as $nama) {
            $this->withoutVite()->get(route($nama))->assertOk()->assertDontSee('SK-RAHASIA-PERUBAHAN')->assertDontSee('perubahan-pkpt/');
        }
    }
}
