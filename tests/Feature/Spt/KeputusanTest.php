<?php

namespace Tests\Feature\Spt;

use App\Actions\Spt\MintaRevisi;
use App\Actions\Spt\TerimaPengajuan;
use App\Actions\Spt\TolakPengajuan;
use App\Enums\KejadianSpt;
use App\Enums\Role;
use App\Enums\StatusPengajuan;
use App\Models\Irban;
use App\Models\NomorCounter;
use App\Models\Spt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Bagian Umum decisions (Fase 4 Task 4): Terima / Perlu Revisi / Tolak through the actions. */
class KeputusanTest extends TestCase
{
    use RefreshDatabase;

    private Irban $irban;

    private User $bu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 23:30', 'Asia/Jakarta'));
        $this->irban = Irban::factory()->create(['nama' => 'Irban II', 'kode_nomor' => 'INSPEKTORAT-II']);
        $this->bu = User::factory()->create(['role' => Role::BagianUmum]);
    }

    private function menunggu(): Spt
    {
        return Spt::factory()->menungguNomor()->create(['irban_id' => $this->irban->id]);
    }

    private function gagal(callable $fn, string $key, string $pesan): void
    {
        try {
            $fn();
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertSame($pesan, $e->errors()[$key][0] ?? null, json_encode($e->errors()));
        }
    }

    public function test_terima_issues_number_and_writes_riwayat(): void
    {
        NomorCounter::create(['tahun' => 2026, 'scope' => 'global'])->forceFill(['nomor_terakhir' => 751])->save();
        $spt = $this->menunggu();

        $hasil = app(TerimaPengajuan::class)->handle($this->bu, $spt);

        $this->assertSame(StatusPengajuan::Diterima, $hasil->status_pengajuan);
        $this->assertSame(752, $hasil->nomor_urut);
        $this->assertSame('700/752/INSPEKTORAT-II/SPT/2026', $hasil->nomor_lengkap);
        $this->assertSame(NomorCounter::first()->id, $hasil->nomor_counter_id);
        $this->assertSame(2026, $hasil->tahun);
        // Decision date is today in Asia/Jakarta (23:30 WIB is still 19 October, 16:30 UTC).
        $this->assertSame('2026-10-19', $hasil->tanggal_spt->toDateString());
        $r = $hasil->riwayat()->latest('id')->first();
        $this->assertSame(KejadianSpt::DITERIMA, $r->kejadian);
        $this->assertSame($this->bu->id, $r->oleh);
        $this->assertSame(['status_pengajuan' => 'menunggu_nomor'], $r->nilai_lama);
        $this->assertSame(['status_pengajuan' => 'diterima', 'nomor_lengkap' => '700/752/INSPEKTORAT-II/SPT/2026'], $r->nilai_baru);
    }

    public function test_two_accepted_in_sequence_get_consecutive_numbers(): void
    {
        $a = app(TerimaPengajuan::class)->handle($this->bu, $this->menunggu());
        $b = app(TerimaPengajuan::class)->handle($this->bu, $this->menunggu());

        $this->assertSame([1, 2], [$a->nomor_urut, $b->nomor_urut]);
        $this->assertSame(2, NomorCounter::first()->nomor_terakhir);
    }

    public function test_minta_revisi_and_tolak_store_catatan(): void
    {
        $spt = app(MintaRevisi::class)->handle($this->bu, $this->menunggu(), '  Periode pemeriksaan belum tepat.  ');
        $this->assertSame(StatusPengajuan::PerluRevisi, $spt->status_pengajuan);
        $this->assertNull($spt->nomor_lengkap);
        $r = $spt->riwayat()->latest('id')->first();
        $this->assertSame([KejadianSpt::PERLU_REVISI, 'Periode pemeriksaan belum tepat.', $this->bu->id], [$r->kejadian, $r->alasan, $r->oleh]);

        $spt = app(TolakPengajuan::class)->handle($this->bu, $this->menunggu(), 'Sudah ada SPT untuk objek ini.');
        $this->assertSame(StatusPengajuan::Ditolak, $spt->status_pengajuan);
        $r = $spt->riwayat()->latest('id')->first();
        $this->assertSame([KejadianSpt::DITOLAK, 'Sudah ada SPT untuk objek ini.'], [$r->kejadian, $r->alasan]);
        $this->assertSame(0, NomorCounter::count());
    }

    public function test_catatan_rules(): void
    {
        foreach ([MintaRevisi::class, TolakPengajuan::class] as $action) {
            $spt = $this->menunggu();
            $this->gagal(fn () => app($action)->handle($this->bu, $spt, ''), 'catatan', 'Catatan minimal 10 karakter.');
            $this->gagal(fn () => app($action)->handle($this->bu, $spt, '  pendek   '), 'catatan', 'Catatan minimal 10 karakter.');
            $this->gagal(fn () => app($action)->handle($this->bu, $spt, str_repeat('a', 1001)), 'catatan', 'Catatan maksimal 1000 karakter.');
            $this->assertSame(StatusPengajuan::MenungguNomor, $spt->refresh()->status_pengajuan);
            $this->assertSame(0, $spt->riwayat()->count());
        }
    }

    public function test_second_decision_on_same_spt_is_a_conflict(): void
    {
        $konflik = 'Pengajuan ini sudah diproses oleh pengguna lain.';
        $spt = $this->menunggu();
        $basi = Spt::find($spt->id); // the other user's stale copy, still menunggu_nomor in memory
        app(TerimaPengajuan::class)->handle($this->bu, $spt);

        $this->gagal(fn () => app(TerimaPengajuan::class)->handle($this->bu, $basi), 'spt', $konflik);
        $this->gagal(fn () => app(MintaRevisi::class)->handle($this->bu, $basi, 'Catatan yang cukup panjang.'), 'spt', $konflik);
        $this->gagal(fn () => app(TolakPengajuan::class)->handle($this->bu, $basi, 'Catatan yang cukup panjang.'), 'spt', $konflik);
        $this->assertSame(1, NomorCounter::first()->nomor_terakhir);
        $this->assertSame(1, $spt->riwayat()->count());

        foreach ([Spt::factory()->create(['irban_id' => $this->irban->id]), Spt::factory()->perluRevisi()->create(), Spt::factory()->ditolak()->create()] as $lain) {
            $this->gagal(fn () => app(TerimaPengajuan::class)->handle($this->bu, $lain), 'spt', $konflik);
        }
    }

    public function test_only_bagian_umum_may_decide(): void
    {
        foreach ([Role::Admin, Role::Pimpinan, Role::Irban] as $role) {
            $u = User::factory()->create(['role' => $role]);
            $spt = $this->menunggu();
            foreach ([fn () => app(TerimaPengajuan::class)->handle($u, $spt), fn () => app(MintaRevisi::class)->handle($u, $spt, 'Catatan yang cukup panjang.'),
                fn () => app(TolakPengajuan::class)->handle($u, $spt, 'Catatan yang cukup panjang.')] as $fn) {
                try {
                    $fn();
                    $this->fail("{$role->value} must not decide");
                } catch (AuthorizationException) {
                    $this->assertSame(StatusPengajuan::MenungguNomor, $spt->refresh()->status_pengajuan);
                }
            }
        }
    }
}
