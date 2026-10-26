<?php

namespace Tests\Feature\Keamanan;

use App\Enums\Role;
use App\Models\Ekspor;
use App\Models\Irban;
use App\Models\Pkpt;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Models\SptDokumen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDef;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Fase 11 Task 1: route-level authorization matrix (auth middleware, role per route-name prefix, ownership of routes with ids). */
class OtorisasiRuteTest extends TestCase
{
    use RefreshDatabase;

    /** Route names reachable without login. Unnamed Livewire asset routes (uri `livewire-*`) and the unnamed POST /masuk are allowed by uri. */
    private const PUBLIK = ['beranda', 'publik.jadwal', 'publik.realisasi', 'login', 'password.*', 'livewire.*', 'default-livewire.*', 'storage.*'];

    private const PEMILIK = ['admin.' => Role::Admin, 'pimpinan.' => Role::Pimpinan, 'irban.' => Role::Irban, 'bagian-umum.' => Role::BagianUmum];

    /** Routes open to every role, with the status each gets. */
    private const BERSAMA = ['profil' => 200, 'notifikasi.index' => 200, 'dashboard' => 302, 'password.force.edit' => 302];

    /** Owner gets a redirect instead of 200: legacy bell link (Route::redirect). */
    private const PENGALIHAN_PEMILIK = ['pimpinan.revisi-jadwal'];

    /** @return array<string, User> keyed by role value */
    private function pengguna(): array
    {
        $irban = Irban::factory()->create();

        return [
            'admin' => User::factory()->admin()->create(),
            'pimpinan' => User::factory()->pimpinan()->create(),
            'irban' => User::factory()->irban()->create(['irban_id' => $irban->id]),
            'bagian_umum' => User::factory()->bagianUmum()->create(),
        ];
    }

    public function test_setiap_rute_internal_butuh_login(): void
    {
        $tanpaAuth = collect(Route::getRoutes()->getRoutes())
            ->reject(fn (RouteDef $r) => $r->getName() ? Str::is(self::PUBLIK, $r->getName()) : (Str::startsWith($r->uri(), 'livewire-') || in_array($r->uri(), ['masuk', 'up'], true)))
            ->reject(fn (RouteDef $r) => in_array('auth', $r->gatherMiddleware(), true))
            ->map(fn (RouteDef $r) => implode('|', $r->methods()).' '.$r->uri())->values()->all();

        $this->assertSame([], $tanpaAuth, 'Rute internal tanpa middleware auth.');
        // The signed e-mail verification link is only valid inside `auth`.
        $this->assertContains('auth', Route::getRoutes()->getByName('profil.email.verifikasi')->gatherMiddleware());
    }

    public function test_matriks_peran(): void
    {
        Pkpt::factory()->create(['tahun' => (int) now('Asia/Jakarta')->year]);
        $pengguna = $this->pengguna();
        $rute = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDef $r) => in_array('GET', $r->methods(), true) && $r->getName() && $r->parameterNames() === []
                && in_array('auth', $r->gatherMiddleware(), true));
        $this->assertGreaterThan(30, $rute->count());

        $galat = [];
        foreach ($rute as $r) {
            $nama = $r->getName();
            $pemilik = collect(self::PEMILIK)->first(fn ($role, $awalan) => Str::startsWith($nama, $awalan));
            foreach ($pengguna as $peran => $user) {
                $harap = match (true) {
                    isset(self::BERSAMA[$nama]) => self::BERSAMA[$nama],
                    $pemilik === null => 'tanpa-pemilik',
                    $pemilik->value !== $peran => 403,
                    in_array($nama, self::PENGALIHAN_PEMILIK, true) => 302,
                    default => 200,
                };
                $status = $this->withoutVite()->actingAs($user)->get(route($nama))->getStatusCode();
                if ($status !== $harap) {
                    $galat[] = "{$nama} sebagai {$peran}: {$status}, harap {$harap}";
                }
            }
        }

        $this->assertSame([], $galat);
    }

    public function test_rute_berparameter_dicek_kepemilikan(): void
    {
        Storage::fake('local');
        $u = $this->pengguna();
        $irbanLain = User::factory()->irban()->create(['irban_id' => Irban::factory()->create()->id]);
        $spt = Spt::factory()->diterima()->create(['irban_id' => $u['irban']->irban_id]);
        Storage::disk('local')->put('spt/a.pdf', '%PDF-1.4');
        $dokumen = SptDokumen::create(['spt_id' => $spt->id, 'jenis' => 'final', 'path' => 'spt/a.pdf', 'nama_asli' => 'a.pdf', 'ukuran' => 8, 'sha256' => str_repeat('a', 64), 'diunggah_oleh' => $u['irban']->id]);
        Storage::disk('local')->put('ekspor/1.xlsx', 'x');
        $ekspor = Ekspor::create(['user_id' => $u['pimpinan']->id, 'jenis' => 'rekap', 'format' => 'xlsx', 'filter' => [], 'nama_file' => 'r.xlsx', 'status' => 'selesai', 'path' => 'ekspor/1.xlsx']);
        $perubahan = PkptPerubahan::factory()->diajukan()->create(['berkas_sk' => 'sk/a.pdf']);
        Storage::disk('local')->put('sk/a.pdf', '%PDF-1.4');

        $rute = [
            'spt.show' => ['spt' => $spt],
            'spt.dokumen' => ['spt' => $spt, 'dokumen' => $dokumen],
            'ekspor.unduh' => ['ekspor' => $ekspor],
            'perubahan-pkpt.sk' => ['perubahan' => $perubahan],
            'admin.pkpt.show' => ['pkpt' => $perubahan->pkpt_id],
            'pimpinan.perubahan-pkpt.show' => ['perubahan' => $perubahan],
        ];
        foreach ($rute as $nama => $param) {
            $this->get(route($nama, $param))->assertRedirect(route('login'));
        }
        auth()->logout();

        // Irban of another irban: the SPT and its document are not theirs.
        $this->actingAs($irbanLain)->get(route('spt.show', $spt))->assertForbidden();
        $this->actingAs($irbanLain)->get(route('spt.dokumen', [$spt, $dokumen]))->assertForbidden();
        $this->withoutVite()->actingAs($u['irban'])->get(route('spt.show', $spt))->assertOk();
        $this->actingAs($u['irban'])->get(route('spt.dokumen', [$spt, $dokumen]))->assertOk();
        // A document of another SPT under this SPT's url.
        $sptLain = Spt::factory()->diterima()->create(['irban_id' => $irbanLain->irban_id]);
        $this->actingAs($irbanLain)->get(route('spt.dokumen', [$sptLain, $dokumen]))->assertNotFound();

        // Another user's export is hidden (404, not 403: its existence is not revealed).
        $this->actingAs($u['admin'])->get(route('ekspor.unduh', $ekspor))->assertNotFound();
        $this->actingAs($u['pimpinan'])->get(route('ekspor.unduh', $ekspor))->assertOk();

        // SK of a package not yet approved: Irban (even of a touched irban) and Bagian Umum are denied; Admin/Pimpinan may read it.
        $this->actingAs($u['irban'])->get(route('perubahan-pkpt.sk', $perubahan))->assertForbidden();
        $this->actingAs($u['bagian_umum'])->get(route('perubahan-pkpt.sk', $perubahan))->assertForbidden();
        $this->actingAs($u['admin'])->get(route('perubahan-pkpt.sk', $perubahan))->assertOk();

        foreach (['pimpinan', 'irban', 'bagian_umum'] as $peran) {
            $this->actingAs($u[$peran])->get(route('admin.pkpt.show', $perubahan->pkpt_id))->assertForbidden();
        }
        foreach (['admin', 'irban', 'bagian_umum'] as $peran) {
            $this->actingAs($u[$peran])->get(route('pimpinan.perubahan-pkpt.show', $perubahan))->assertForbidden();
        }
        $this->withoutVite()->actingAs($u['admin'])->get(route('admin.pkpt.show', $perubahan->pkpt_id))->assertOk();
        $this->withoutVite()->actingAs($u['pimpinan'])->get(route('pimpinan.perubahan-pkpt.show', $perubahan))->assertOk();
    }
}
