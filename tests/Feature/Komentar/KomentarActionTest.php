<?php

namespace Tests\Feature\Komentar;

use App\Actions\Komentar\BalasKomentar;
use App\Actions\Komentar\TulisKomentar;
use App\Actions\Komentar\TutupPermintaan;
use App\Enums\StatusPermintaan;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Komentar;
use App\Models\Pegawai;
use App\Models\Spt;
use App\Models\User;
use App\Support\DiinputOleh;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KomentarActionTest extends TestCase
{
    use RefreshDatabase;

    private Spt $spt;

    private User $pimpinan;

    private User $irban;

    private Pegawai $penginput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 3, 25));
        $this->spt = Spt::factory()->diterima()->create();
        $this->pimpinan = User::factory()->pimpinan()->create();
        $this->irban = User::factory()->irban()->create();
        $this->irban->forceFill(['irban_id' => $this->spt->irban_id])->save();
        $this->penginput = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => $this->spt->irban_id, 'tahun' => 2026, 'ppj_pegawai_id' => $this->penginput->id]);
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

    private function tulis(array $data = [], ?User $user = null, ?Spt $spt = null): Komentar
    {
        return app(TulisKomentar::class)->handle($user ?? $this->pimpinan, $spt ?? $this->spt, ['isi' => 'Mohon dijelaskan.', 'minta_penjelasan' => false, ...$data]);
    }

    private function balas(Komentar $root, array $data = [], ?User $user = null): Komentar
    {
        return app(BalasKomentar::class)->handle($user ?? $this->irban, $this->spt, ['parent_id' => $root->id, 'isi' => 'Baik, Pak.', 'diinput_oleh' => $this->penginput->id, ...$data]);
    }

    private function tutup(Komentar $root, ?User $user = null, ?Spt $spt = null): Komentar
    {
        return app(TutupPermintaan::class)->handle($user ?? $this->pimpinan, $spt ?? $this->spt, ['komentar_id' => $root->id]);
    }

    public function test_root_with_and_without_minta_penjelasan(): void
    {
        $biasa = $this->tulis();
        $this->assertNull($biasa->parent_id);
        $this->assertFalse($biasa->minta_penjelasan);
        $this->assertNull($biasa->status_permintaan);
        $this->assertSame($this->pimpinan->id, $biasa->user_id);

        $minta = $this->tulis(['minta_penjelasan' => 'true']);
        $this->assertTrue($minta->fresh()->minta_penjelasan);
        $this->assertSame(StatusPermintaan::Terbuka, $minta->fresh()->status_permintaan);
        $this->assertSame('Terbuka', StatusPermintaan::Terbuka->label());
        $this->assertSame('Selesai', StatusPermintaan::Selesai->label());
    }

    public function test_isi_validation_and_trim(): void
    {
        foreach (['', "  \n "] as $kosong) {
            $this->gagal(fn () => $this->tulis(['isi' => $kosong]), 'isi', 'Tulis isi komentar.');
        }
        $this->gagal(fn () => $this->tulis(['isi' => str_repeat('a', 2001)]), 'isi', 'Komentar maksimal 2000 karakter.');
        $this->assertSame(0, Komentar::count());

        $this->assertSame(2000, mb_strlen($this->tulis(['isi' => str_repeat('a', 2000)])->isi));
        $root = $this->tulis(['isi' => "  rapi \n"]);
        $this->assertSame('rapi', $root->isi);

        $this->gagal(fn () => $this->balas($root, ['isi' => ' ']), 'isi', 'Tulis isi komentar.');
        $this->gagal(fn () => $this->balas($root, ['isi' => str_repeat('a', 2001)]), 'isi', 'Komentar maksimal 2000 karakter.');
        $this->assertSame(0, Komentar::whereNotNull('parent_id')->count());
    }

    public function test_only_authorized_roles_write(): void
    {
        foreach ([$this->irban, User::factory()->bagianUmum()->create(), User::factory()->admin()->create()] as $u) {
            try {
                $this->tulis([], $u);
                $this->fail('refused');
            } catch (AuthorizationException) {
                $this->assertSame(0, Komentar::count());
            }
        }
        $root = $this->tulis();
        $this->expectException(AuthorizationException::class);
        $this->balas($root, [], User::factory()->admin()->create());
    }

    public function test_reply_goes_to_a_root_of_the_same_spt_only(): void
    {
        $root = $this->tulis();
        $balasan = $this->balas($root);
        $this->assertSame($root->id, $balasan->parent_id);
        $this->assertFalse($balasan->fresh()->minta_penjelasan);
        $this->assertNull($balasan->status_permintaan);

        $pesan = 'Komentar yang dibalas tidak ditemukan.';
        $this->gagal(fn () => $this->balas($balasan), 'komentar', $pesan);
        $lain = $this->tulis([], null, Spt::factory()->diterima()->create());
        $this->gagal(fn () => $this->balas($lain), 'komentar', $pesan);
        $this->gagal(fn () => $this->balas($root, ['parent_id' => 999999]), 'komentar', $pesan);
        $this->assertSame(2, Komentar::where('spt_id', $this->spt->id)->count());
    }

    public function test_irban_reply_needs_valid_diinput_oleh(): void
    {
        $root = $this->tulis();
        $this->gagal(fn () => $this->balas($root, ['diinput_oleh' => null]), 'diinput_oleh', 'Pilih nama penginput terlebih dahulu');
        $this->gagal(fn () => $this->balas($root, ['diinput_oleh' => Pegawai::factory()->create()->id]), 'diinput_oleh', 'Penginput harus PPJ atau anggota aktif Irban ini tahun 2026.');
        $anggotaLain = Pegawai::factory()->create();
        IrbanTahunan::factory()->create(['irban_id' => Irban::factory()->create()->id, 'tahun' => 2026, 'ppj_pegawai_id' => $anggotaLain->id]);
        $this->gagal(fn () => $this->balas($root, ['diinput_oleh' => $anggotaLain->id]), 'diinput_oleh', 'Penginput harus PPJ atau anggota aktif Irban ini tahun 2026.');
        $this->assertSame(1, Komentar::count());

        $ok = $this->balas($root);
        $this->assertSame($this->penginput->id, $ok->fresh()->diinput_oleh);
        $this->assertSame($this->penginput->id, DiinputOleh::terakhir($this->irban));
    }

    public function test_non_irban_reply_ignores_diinput_oleh(): void
    {
        $root = $this->tulis();
        $bu = User::factory()->bagianUmum()->create();
        $this->assertNull($this->balas($root, ['diinput_oleh' => $this->penginput->id], $bu)->fresh()->diinput_oleh);
        $this->assertNull($this->balas($root, ['diinput_oleh' => null], $this->pimpinan)->fresh()->diinput_oleh);
    }

    public function test_close_rules_and_messages(): void
    {
        $biasa = $this->tulis();
        $this->gagal(fn () => $this->tutup($biasa), 'komentar', 'Komentar ini bukan permintaan penjelasan.');

        $minta = $this->tulis(['minta_penjelasan' => true]);
        $aksi = app(TutupPermintaan::class);
        $belum = 'Permintaan baru dapat ditutup setelah Irban membalas.';
        $this->assertSame($belum, $aksi->bisa($minta));
        $this->gagal(fn () => $this->tutup($minta), 'komentar', $belum);
        // A Bagian Umum or pimpinan reply does not count.
        $this->balas($minta, [], User::factory()->bagianUmum()->create());
        $this->balas($minta, [], $this->pimpinan);
        $this->assertSame($belum, $aksi->bisa($minta->fresh()));

        $this->balas($minta);
        $this->assertNull($aksi->bisa($minta->fresh()));
        $hasil = $this->tutup($minta)->fresh();
        $this->assertSame(StatusPermintaan::Selesai, $hasil->status_permintaan);
        $this->assertSame($this->pimpinan->id, $hasil->ditutup_oleh);
        $this->assertNotNull($hasil->ditutup_pada);

        $this->assertSame('Permintaan penjelasan sudah ditutup.', $aksi->bisa($minta->fresh()));
        $this->gagal(fn () => $this->tutup($minta), 'komentar', 'Permintaan penjelasan sudah ditutup.');
        $this->assertSame('Komentar ini bukan permintaan penjelasan.', $aksi->bisa($biasa));
    }

    public function test_close_is_scoped_to_spt_and_role(): void
    {
        $minta = $this->tulis(['minta_penjelasan' => true]);
        $this->balas($minta);
        $this->gagal(fn () => $this->tutup($minta, null, Spt::factory()->diterima()->create()), 'komentar', 'Permintaan penjelasan tidak ditemukan.');
        foreach ([$this->irban, User::factory()->bagianUmum()->create()] as $u) {
            try {
                $this->tutup($minta, $u);
                $this->fail('refused');
            } catch (AuthorizationException) {
                $this->assertSame(StatusPermintaan::Terbuka, $minta->fresh()->status_permintaan);
            }
        }
        $kedua = User::factory()->pimpinan()->create();
        $this->assertSame($kedua->id, $this->tutup($minta, $kedua)->ditutup_oleh);
    }

    public function test_reply_allowed_on_closed_permintaan_and_comments_on_selesai_spt(): void
    {
        $minta = $this->tulis(['minta_penjelasan' => true]);
        $this->balas($minta);
        $this->tutup($minta);
        $this->balas($minta, ['isi' => 'Tambahan.']);
        $this->assertSame(2, $minta->balasan()->count());

        $selesai = Spt::factory()->selesai()->create();
        $this->assertTrue($this->tulis(['minta_penjelasan' => true], null, $selesai)->exists);
    }

    public function test_scope_counts_only_open_roots(): void
    {
        $terbuka = $this->tulis(['minta_penjelasan' => true]);
        $this->tulis(); // plain comment
        $ditutup = $this->tulis(['minta_penjelasan' => true]);
        $this->balas($ditutup);
        $this->tutup($ditutup);
        $this->balas($terbuka); // replies never count

        $this->assertSame([$terbuka->id], Komentar::permintaanTerbuka()->pluck('id')->all());
        $this->assertSame(3, $this->spt->komentar()->whereNull('parent_id')->count());
        $this->assertSame(1, $terbuka->balasan()->count());
    }
}
