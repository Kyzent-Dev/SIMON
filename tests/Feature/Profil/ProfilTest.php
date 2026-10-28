<?php

namespace Tests\Feature\Profil;

use App\Actions\Profil\SimpanGambarProfil;
use App\Livewire\Admin\Pengguna;
use App\Livewire\Profil;
use App\Models\Aktivitas;
use App\Models\Irban;
use App\Models\IrbanTahunan;
use App\Models\Pegawai;
use App\Models\User;
use App\Notifications\EmailDiubah;
use App\Notifications\KataSandiDiubah;
use App\Notifications\VerifikasiEmailBaru;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    private User $irban;

    private Pegawai $penginput;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Storage::fake('public');
        Notification::fake();
        $this->travelTo(now('Asia/Jakarta')->setDate(2026, 3, 25));
        $irban = Irban::factory()->create(['nama' => 'Irban I']);
        $this->irban = User::factory()->irban()->create(['irban_id' => $irban->id, 'name' => 'Akun Irban I', 'username' => 'irban1', 'email' => 'irban1@example.test']);
        $this->penginput = Pegawai::factory()->create(['nama_bergelar' => 'Budi Santoso, S.E.']);
        IrbanTahunan::factory()->create(['irban_id' => $irban->id, 'tahun' => 2026, 'ppj_pegawai_id' => $this->penginput->id]);
    }

    private function gambar(int $w, int $h): UploadedFile
    {
        return UploadedFile::fake()->image('a.jpg', $w, $h);
    }

    private function ukuran(?string $path): array
    {
        $info = getimagesize(Storage::disk('public')->path($path));

        return [$info[0], $info[1], $info['mime']];
    }

    private function sandiBaru(User $user, array $over = [])
    {
        return Livewire::actingAs($user)->test(Profil::class)
            ->set($over + ['password_lama' => 'password', 'password' => 'BaruSekali123', 'password_confirmation' => 'BaruSekali123']);
    }

    public function test_every_role_opens_profil_and_guests_are_redirected(): void
    {
        $this->get('/profil')->assertRedirect('/masuk');
        foreach (['admin', 'pimpinan', 'bagianUmum'] as $role) {
            $this->actingAs(User::factory()->$role()->create())->get('/profil')->assertOk()
                ->assertSee('Foto profil')->assertSee('Latar profil')->assertSee('Nama tampilan')->assertSeeHtml('id="kata-sandi"');
        }
        $this->actingAs($this->irban)->get('/profil')->assertOk()->assertSee('Hubungi Admin untuk mengubah')
            ->assertDontSeeHtml('wire:model="username"')->assertSee('Diinput oleh');
    }

    public function test_irban_cannot_change_name_username_or_email_via_livewire(): void
    {
        Livewire::actingAs($this->irban)->test(Profil::class)
            ->set('name', 'Diretas')->set('username', 'diretas')->set('email', 'baru@example.test')
            ->call('simpanAkun')->assertForbidden();

        $this->irban->refresh();
        $this->assertSame(['Akun Irban I', 'irban1', 'irban1@example.test', null],
            [$this->irban->name, $this->irban->username, $this->irban->email, $this->irban->email_baru]);
        Notification::assertNothingSent();
    }

    public function test_irban_upload_needs_a_valid_penginput_and_is_logged(): void
    {
        $c = Livewire::actingAs($this->irban)->test(Profil::class)->set('foto', $this->gambar(400, 400));
        $c->call('simpanGambar')->assertHasErrors(['diinput_oleh' => 'Pilih nama penginput terlebih dahulu']);
        $lain = Pegawai::factory()->create();
        $c->set('diinput_oleh', $lain->id)->call('simpanGambar')->assertHasErrors(['diinput_oleh']);
        $this->assertNull($this->irban->fresh()->foto_profil);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $c->set('diinput_oleh', $this->penginput->id)->call('simpanGambar')->assertHasNoErrors()->assertRedirect(route('profil'));
        $this->assertNotNull($this->irban->fresh()->foto_profil);
        $this->assertSame([$this->irban->id, $this->penginput->id], array_values(Aktivitas::where('event', 'profil.foto')->sole()->only(['subject_id', 'diinput_oleh'])));

        Livewire::actingAs($this->irban)->test(Profil::class)->assertSet('diinput_oleh', $this->penginput->id)
            ->set('diinput_oleh', null)->call('hapusGambar', 'foto')->assertHasErrors(['diinput_oleh']);
        $this->assertNotNull($this->irban->fresh()->foto_profil);
    }

    public function test_foto_and_latar_are_webp_of_exact_size_and_old_files_are_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test(Profil::class)
            ->set('foto', $this->gambar(900, 500))->set('latar', $this->gambar(2400, 1200))->call('simpanGambar')->assertHasNoErrors();
        $admin->refresh();
        $this->assertSame([256, 256, 'image/webp'], $this->ukuran($admin->foto_profil));
        $this->assertSame([1600, 400, 'image/webp'], $this->ukuran($admin->latar_profil));
        $this->assertStringStartsWith('profil/', $admin->foto_profil);
        $lama = $admin->foto_profil;

        Livewire::actingAs($admin)->test(Profil::class)->set('foto', $this->gambar(300, 300))->call('simpanGambar')->assertHasNoErrors();
        Storage::disk('public')->assertMissing($lama);
        Storage::disk('public')->assertExists($admin->fresh()->foto_profil);

        Livewire::actingAs($admin)->test(Profil::class)->call('hapusGambar', 'foto')->call('hapusGambar', 'latar');
        $this->assertNull($admin->fresh()->foto_profil);
        $this->assertNull($admin->fresh()->latar_profil);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_bad_images_are_field_errors(): void
    {
        $c = Livewire::actingAs(User::factory()->pimpinan()->create())->test(Profil::class);
        $c->set('foto', $this->gambar(200, 300))->assertHasErrors(['foto' => 'Ukuran gambar minimal 256 × 256 piksel.']);
        $c->set('latar', $this->gambar(1200, 150))->assertHasErrors(['latar' => 'Ukuran gambar minimal 800 × 200 piksel.']);
        $c->set('foto', $this->gambar(6001, 300))->assertHasErrors(['foto' => 'Ukuran gambar maksimal 6000 × 6000 piksel.']);
        $c->set('foto', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->assertHasErrors(['foto']);
        $rusak = UploadedFile::fake()->createWithContent('rusak.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00\x13garbage", 400));
        $c->set('foto', $rusak)->call('simpanGambar')->assertHasErrors(['foto']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_email_change_is_verified_by_a_signed_link(): void
    {
        $user = User::factory()->pimpinan()->create(['email' => 'lama@example.test', 'username' => 'pimpinan']);
        Livewire::actingAs($user)->test(Profil::class)->set('email', 'Baru@Example.test')->call('simpanAkun')->assertHasNoErrors()
            ->assertSee('Menunggu verifikasi: baru@example.test');
        $this->assertSame(['lama@example.test', 'baru@example.test'], [$user->fresh()->email, $user->fresh()->email_baru]);

        $tautan = null;
        Notification::assertSentOnDemand(VerifikasiEmailBaru::class, function ($n, $channels, AnonymousNotifiable $to) use (&$tautan) {
            $tautan = $n->toMail($to)->actionUrl;

            return $channels === ['mail'] && $to->routes === ['mail' => 'baru@example.test'];
        });
        Notification::assertSentOnDemandTimes(VerifikasiEmailBaru::class, 1);

        $this->actingAs(User::factory()->admin()->create())->get($tautan)->assertForbidden();
        $this->actingAs($user)->get($tautan)->assertRedirect(route('profil'))->assertSessionHas('toast.type', 'success');
        $this->assertSame(['baru@example.test', null], [$user->fresh()->email, $user->fresh()->email_baru]);
        Notification::assertSentOnDemand(EmailDiubah::class, fn ($n, $c, $to) => $to->routes === ['mail' => 'lama@example.test']);

        // Reused link.
        $this->actingAs($user->fresh())->get($tautan)->assertRedirect(route('profil'))
            ->assertSessionHas('toast', ['type' => 'error', 'message' => 'Tautan tidak berlaku atau sudah dipakai.']);
    }

    public function test_pending_email_can_be_resent_with_a_new_link_and_the_throttle_holds(): void
    {
        $user = User::factory()->pimpinan()->create(['email' => 'lama@example.test']);
        $c = Livewire::actingAs($user)->test(Profil::class)->assertDontSee('Kirim ulang tautan');
        $c->set('email', 'baru@example.test')->call('simpanAkun')->assertSee('Kirim ulang tautan');

        $c->call('kirimUlangEmail')->assertHasNoErrors()->assertDispatched('toast', type: 'success', message: 'Tautan verifikasi dikirim ulang ke baru@example.test.');
        Notification::assertSentOnDemandTimes(VerifikasiEmailBaru::class, 2);

        // The resent link replaces the first one (new nonce): the first is rejected, the second works.
        $tautan = [];
        Notification::assertSentOnDemand(VerifikasiEmailBaru::class, function ($n, $ch, AnonymousNotifiable $to) use (&$tautan) {
            $tautan[] = $n->toMail($to)->actionUrl;

            return true;
        });
        $this->assertCount(2, $tautan);
        $this->actingAs($user)->get($tautan[0])->assertSessionHas('toast', ['type' => 'error', 'message' => 'Tautan tidak berlaku atau sudah dipakai.']);
        $this->actingAs($user)->get($tautan[1])->assertSessionHas('toast.type', 'success');
        $this->assertSame('baru@example.test', $user->fresh()->email);

        // Throttle: simpanAkun + kirimUlang above used 2 of 3; one more is allowed, the fourth is refused.
        $user->forceFill(['email_baru' => 'lagi@example.test'])->save();
        $c = Livewire::actingAs($user->fresh())->test(Profil::class);
        $c->call('kirimUlangEmail')->assertHasNoErrors();
        $c->call('kirimUlangEmail')->assertHasErrors(['email']);
        Notification::assertSentOnDemandTimes(VerifikasiEmailBaru::class, 3);
    }

    public function test_resend_needs_a_pending_request_and_a_non_irban_owner(): void
    {
        Livewire::actingAs(User::factory()->pimpinan()->create())->test(Profil::class)->call('kirimUlangEmail')->assertForbidden();
        Livewire::actingAs(User::factory()->irban()->create(['email_baru' => 'x@example.test']))->test(Profil::class)->assertDontSee('Kirim ulang tautan')->call('kirimUlangEmail')->assertForbidden();
        Notification::assertNothingSent();
    }

    public function test_expired_or_tampered_link_is_rejected(): void
    {
        $user = User::factory()->pimpinan()->create(['email' => 'lama@example.test']);
        Livewire::actingAs($user)->test(Profil::class)->set('email', 'baru@example.test')->call('simpanAkun');
        $tautan = null;
        Notification::assertSentOnDemand(VerifikasiEmailBaru::class, function ($n, $c, $to) use (&$tautan) {
            $tautan = $n->toMail($to)->actionUrl;

            return true;
        });

        $palsu = preg_replace('#/[0-9a-f]{40}\?#', '/'.str_repeat('0', 40).'?', $tautan);
        $this->assertNotSame($tautan, $palsu);
        $pesan = ['type' => 'error', 'message' => 'Tautan verifikasi sudah kedaluwarsa atau tidak sah. Ajukan penggantian email lagi dari Profil.'];
        $this->actingAs($user)->get($palsu)->assertRedirect(route('profil'))->assertSessionHas('toast', $pesan);
        $this->travel(61)->minutes();
        $this->actingAs($user)->get($tautan)->assertRedirect(route('profil'))->assertSessionHas('toast', $pesan);
        $this->assertSame('lama@example.test', $user->fresh()->email);
    }

    public function test_an_older_link_is_void_after_a_newer_request_for_the_same_address(): void
    {
        $user = User::factory()->pimpinan()->create(['email' => 'lama@example.test']);
        $tautan = [];
        $c = Livewire::actingAs($user)->test(Profil::class);
        foreach (['x@example.test', 'y@example.test', 'x@example.test'] as $email) {
            $c->set('email', $email)->call('simpanAkun')->assertHasNoErrors();
        }
        Notification::assertSentOnDemand(VerifikasiEmailBaru::class, function ($n, $c, $to) use (&$tautan) {
            $tautan[$to->routes['mail']][] = $n->toMail($to)->actionUrl;

            return true;
        });
        $this->assertCount(2, $tautan['x@example.test']);

        $this->actingAs($user)->get($tautan['x@example.test'][0])->assertSessionHas('toast.message', 'Tautan tidak berlaku atau sudah dipakai.');
        $this->assertSame('lama@example.test', $user->fresh()->email);
        $this->actingAs($user)->get($tautan['x@example.test'][1])->assertSessionHas('toast.type', 'success');
        $this->assertSame('x@example.test', $user->fresh()->email);
    }

    public function test_changing_the_name_reloads_the_page_so_the_header_follows(): void
    {
        $user = User::factory()->pimpinan()->create(['name' => 'Nama Lama']);
        Livewire::actingAs($user)->test(Profil::class)->set('name', 'Nama Baru')->call('simpanAkun')->assertHasNoErrors()->assertRedirect(route('profil'));
        $this->assertSame('Nama Baru', $user->fresh()->name);
        $this->actingAs($user->fresh())->get('/profil')->assertSee('Menu pengguna Nama Baru');

        // Control: no name change, no reload.
        Livewire::actingAs($user->fresh())->test(Profil::class)->set('username', 'ganti.saja')->call('simpanAkun')->assertNoRedirect();
    }

    public function test_transparent_png_is_flattened_onto_white(): void
    {
        $im = imagecreatetruecolor(300, 300);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, (int) imagecolorallocatealpha($im, 0, 0, 0, 127));
        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();

        $admin = User::factory()->admin()->create();
        Livewire::actingAs($admin)->test(Profil::class)
            ->set('foto', UploadedFile::fake()->createWithContent('t.png', $png))->call('simpanGambar')->assertHasNoErrors();
        $out = imagecreatefromwebp(Storage::disk('public')->path($admin->fresh()->foto_profil));
        $this->assertGreaterThan(0xF8F8F8, imagecolorat($out, 128, 128) & 0xFFFFFF);
    }

    public function test_storage_failure_is_labelled_as_saving_not_reading(): void
    {
        $disk = \Mockery::mock();
        $disk->shouldReceive('put')->andReturn(false);
        $disk->shouldReceive('delete');
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $admin = User::factory()->admin()->create();
        Exceptions::fake();

        try {
            app(SimpanGambarProfil::class)->handle($admin, 'foto', $this->gambar(300, 300));
            $this->fail('ValidationException expected');
        } catch (ValidationException $e) {
            $this->assertSame(['foto' => ['Gambar gagal disimpan. Coba lagi.']], $e->errors());
        }
        Exceptions::assertReported(\RuntimeException::class);
    }

    public function test_irban_notice_ends_with_exactly_one_period(): void
    {
        foreach (['Budi' => 'Diinput oleh Budi.', 'Budi Santoso, S.E.' => 'Diinput oleh Budi Santoso, S.E.'] as $nama => $baris) {
            $this->assertContains($baris, (new KataSandiDiubah('irban1', $nama))->toMail($this->irban)->introLines);
        }
    }

    public function test_email_must_be_unique_against_emails_and_pending_changes(): void
    {
        User::factory()->create(['email' => 'dipakai@example.test']);
        User::factory()->create(['email_baru' => 'menunggu@example.test']);
        $user = User::factory()->admin()->create();
        $c = Livewire::actingAs($user)->test(Profil::class);
        $c->set('email', 'dipakai@example.test')->call('simpanAkun')->assertHasErrors(['email' => 'Email sudah dipakai pengguna lain.']);
        $c->set('email', 'menunggu@example.test')->call('simpanAkun')->assertHasErrors(['email' => 'Email sudah dipakai pengguna lain.']);
        $c->set('email', $user->email)->set('username', 'Bukan Valid')->call('simpanAkun')->assertHasErrors(['username']);
        $c->set('username', 'admin.baru')->set('name', 'Admin Baru')->call('simpanAkun')->assertHasNoErrors();
        $this->assertSame(['Admin Baru', 'admin.baru', null], [$user->fresh()->name, $user->fresh()->username, $user->fresh()->email_baru]);
        Notification::assertNothingSent();
    }

    public function test_password_change_rules(): void
    {
        $user = User::factory()->pimpinan()->create();
        $this->sandiBaru($user, ['password_lama' => 'salah'])->call('kirimSandi')->assertHasErrors(['password_lama' => 'Kata sandi lama salah.']);
        $this->sandiBaru($user, ['password' => 'password', 'password_confirmation' => 'password'])->call('kirimSandi')->assertHasErrors(['password']);
        $this->sandiBaru($user, ['password' => 'Password123', 'password_confirmation' => 'Password123'])
            ->call('kirimSandi')->assertHasNoErrors();
        $this->sandiBaru($user->fresh(), ['password_lama' => 'Password123'])->call('kirimSandi')->assertHasNoErrors();
        $this->sandiBaru($user->fresh(), ['password_lama' => 'BaruSekali123'])->call('kirimSandi')
            ->assertHasErrors(['password' => __('passwords.different')]);
    }

    public function test_password_change_logs_out_other_sessions_and_mails_the_account(): void
    {
        $user = User::factory()->pimpinan()->create(['username' => 'pimpinan']);
        $lama = $user->password;
        $this->actingAs($user)->withSession(['password_hash_web' => $lama])->get('/profil')->assertOk();

        $this->sandiBaru($user)->call('kirimSandi')->assertHasNoErrors();
        $this->assertTrue(Hash::check('BaruSekali123', $user->fresh()->password));

        // A second device still carries the old hash in its session: logged out.
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $lama])->get('/profil')->assertRedirect('/masuk');
        $this->assertGuest();
        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $user->fresh()->password])->get('/profil')->assertOk();

        Notification::assertSentTo($user, KataSandiDiubah::class, fn ($n, $channels) => $channels === ['mail']
            && ! str_contains(implode(' ', $n->toMail($user)->introLines), 'Diinput oleh'));
    }

    public function test_irban_password_change_confirms_first_needs_penginput_and_names_them(): void
    {
        $c = $this->sandiBaru($this->irban)->call('kirimSandi')->assertSet('sandiOpen', true)
            ->assertSee('Semua anggota yang sedang masuk dengan akun ini akan keluar.');
        $this->assertTrue(Hash::check('password', $this->irban->fresh()->password));

        $c->call('kirimSandi')->assertHasErrors(['diinput_oleh'])->assertSet('sandiOpen', true);
        $c->set('diinput_oleh', $this->penginput->id)->call('kirimSandi')->assertHasNoErrors()->assertSet('sandiOpen', false);
        $this->assertTrue(Hash::check('BaruSekali123', $this->irban->fresh()->password));

        $this->assertSame([$this->irban->id, $this->penginput->id], array_values(Aktivitas::where('event', 'profil.kata_sandi')->sole()->only(['subject_id', 'diinput_oleh'])));
        Notification::assertSentTo($this->irban, KataSandiDiubah::class,
            fn ($n) => str_contains(implode(' ', $n->toMail($this->irban)->introLines), 'Diinput oleh Budi Santoso, S.E.'));
    }

    public function test_header_shows_avatar_image_when_set_and_initials_otherwise(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Rina Wati']);
        $this->actingAs($user)->get('/admin')->assertSee('Menu pengguna Rina Wati')->assertSee('RW')
            ->assertSee('Ubah kata sandi');
        $this->assertDoesNotMatchRegularExpression('/<img[^>]*data-avatar/', $this->actingAs($user)->get('/admin')->getContent());

        $user->forceFill(['foto_profil' => 'profil/x.webp'])->save();
        $html = $this->actingAs($user)->get('/admin')->getContent();
        $this->assertMatchesRegularExpression('/<img[^>]*src="'.preg_quote(Storage::disk('public')->url('profil/x.webp'), '/').'"[^>]*data-avatar/', $html);
    }

    public function test_admin_reset_logs_out_the_users_other_sessions(): void
    {
        $user = User::factory()->pimpinan()->create();
        $lama = $user->password;
        Livewire::actingAs(User::factory()->admin()->create())->test(Pengguna::class)
            ->call('confirmReset', $user->id)->assertSee('Semua sesi pengguna ini yang sedang masuk akan keluar.')
            ->set('sementara', 'Sementara123')->call('aturUlang')->assertHasNoErrors();

        $this->actingAs($user->fresh())->withSession(['password_hash_web' => $lama])->get('/pimpinan')->assertRedirect('/masuk');
    }
}
