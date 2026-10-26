<?php

namespace Tests\Feature\Demo;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/** E11 `simon:buat-admin`: first Admin of a fresh install, same rules as SimpanPengguna, password never shown or logged. */
class BuatAdminTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'RahasiaAwal2026';

    private function jalankan(string $username, string $sandi, string $ulang)
    {
        return $this->artisan('simon:buat-admin', ['username' => $username, '--nama' => 'Admin Utama', '--email' => "{$username}@muaraenimkab.go.id"])
            ->expectsQuestion('Kata sandi', $sandi)
            ->expectsQuestion('Ulangi kata sandi', $ulang)
            ->doesntExpectOutputToContain($sandi);
    }

    public function test_membuat_admin(): void
    {
        $this->jalankan('kepala.ti', self::SANDI, self::SANDI)->expectsOutput('Admin kepala.ti dibuat.')->assertExitCode(0);

        $u = User::where('username', 'kepala.ti')->firstOrFail();
        $this->assertSame(Role::Admin, $u->role);
        $this->assertSame('Admin Utama', $u->name);
        $this->assertFalse($u->wajib_ganti_password);
        $this->assertTrue(Hash::check(self::SANDI, $u->password));

        $log = Activity::where('subject_type', $u->getMorphClass())->where('subject_id', $u->id)->get();
        $this->assertNotEmpty($log);
        foreach ($log as $a) {
            $this->assertArrayNotHasKey('password', $a->properties['attributes'] ?? []);
            $this->assertStringNotContainsString(self::SANDI, json_encode($a->properties));
        }
    }

    public function test_sandi_tidak_sama(): void
    {
        $this->jalankan('kepala.ti', self::SANDI, self::SANDI.'x')->expectsOutput('Kata sandi tidak sama.')->assertExitCode(1);
        $this->assertSame(0, User::count());
    }

    public function test_username_tidak_valid_dan_sandi_lemah(): void
    {
        User::factory()->create(['username' => 'kepala.ti']);
        $this->jalankan('kepala.ti', self::SANDI, self::SANDI)->expectsOutput('Username sudah dipakai.')->assertExitCode(1);

        $this->jalankan('Kepala TI', self::SANDI, self::SANDI)
            ->expectsOutput('Username 3–30 karakter: huruf kecil, angka, titik, atau garis bawah.')->assertExitCode(1);

        $this->jalankan('admin.baru', 'pendek', 'pendek')->expectsOutput(__('validation.min.string', ['attribute' => __('validation.attributes.password'), 'min' => 8]))
            ->assertExitCode(1);
        $this->assertSame(1, User::count());
    }
}
