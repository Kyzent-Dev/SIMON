<?php

namespace App\Livewire;

use App\Actions\Profil\GantiKataSandi;
use App\Actions\Profil\HapusGambarProfil;
use App\Actions\Profil\MintaGantiEmail;
use App\Actions\Profil\SimpanGambarProfil;
use App\Actions\Profil\UbahDataAkun;
use App\Enums\Role;
use App\Models\User;
use App\Services\Settings;
use App\Support\DiinputOleh;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Profil (spec 7.2, K8): every role edits its own account; Irban accounts only foto, latar and password, each with "Diinput oleh". Restrictions live in the actions. */
#[Title('Profil')]
class Profil extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public ?TemporaryUploadedFile $foto = null;

    public ?TemporaryUploadedFile $latar = null;

    public string $password_lama = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $diinput_oleh = null;

    public bool $ganti = false;

    /** Irban: the confirm modal before the password change. */
    public bool $sandiOpen = false;

    public function mount(): void
    {
        $user = $this->pengguna();
        $this->name = $user->name;
        $this->username = $user->username;
        $this->email = $user->email;
        $this->diinput_oleh = DiinputOleh::terakhir($user);
    }

    /** The signed-in account; the Livewire update endpoint does not run the route's auth middleware. */
    private function pengguna(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    public function gantiPenginput(): void
    {
        $this->pengguna();
        $this->ganti = true;
    }

    /** Check a chosen image at once, so a wrong file is reported before Simpan. */
    public function updated(string $prop): void
    {
        if (in_array($prop, ['foto', 'latar'], true)) {
            $this->pengguna();
            $this->validateOnly($prop, [$prop => SimpanGambarProfil::rules($prop)]);
        }
    }

    public function simpanAkun(UbahDataAkun $action): void
    {
        $user = $this->pengguna();
        $this->resetValidation();
        $namaLama = $user->name;
        $dikirim = $action->handle($user, ['name' => $this->name, 'username' => $this->username, 'email' => $this->email]);
        $this->email = $user->email;
        $pesan = $dikirim ? "Data akun disimpan. Tautan verifikasi dikirim ke {$user->email_baru}." : 'Data akun disimpan.';
        // The header (outside this component) shows the name: reload when it changed.
        if ($user->name !== $namaLama) {
            $this->muatUlang($pesan);

            return;
        }
        $this->dispatch('toast', type: 'success', message: $pesan);
    }

    public function kirimUlangEmail(MintaGantiEmail $action): void
    {
        $user = $this->pengguna();
        $this->resetValidation();
        $action->kirimUlang($user);
        $this->dispatch('toast', type: 'success', message: "Tautan verifikasi dikirim ulang ke {$user->email_baru}.");
    }

    public function simpanGambar(SimpanGambarProfil $action): void
    {
        $user = $this->pengguna();
        $this->resetValidation();
        if (! $this->foto && ! $this->latar) {
            throw ValidationException::withMessages(['foto' => 'Pilih foto profil atau latar profil terlebih dahulu.']);
        }
        foreach (['foto', 'latar'] as $jenis) {
            if ($this->{$jenis}) {
                $action->handle($user, $jenis, $this->{$jenis}, $this->diinput_oleh);
                $this->reset($jenis);
            }
        }
        $this->muatUlang('Gambar profil disimpan.');
    }

    /** Reload the page (wire:navigate) so the header avatar, outside this component, shows the change too. */
    private function muatUlang(string $pesan): void
    {
        session()->flash('toast', ['type' => 'success', 'message' => $pesan]);
        $this->redirectRoute('profil', navigate: true);
    }

    public function hapusGambar(string $jenis, HapusGambarProfil $action): void
    {
        $user = $this->pengguna();
        $this->resetValidation();
        abort_unless(in_array($jenis, ['foto', 'latar'], true), 404);
        $action->handle($user, $jenis, $this->diinput_oleh);
        $this->muatUlang($jenis === 'foto' ? 'Foto profil dihapus.' : 'Latar profil dihapus.');
    }

    /** Irban: open the confirm modal ("Semua anggota … akan keluar."); others change at once. */
    public function kirimSandi(GantiKataSandi $action): void
    {
        $user = $this->pengguna();
        $this->resetValidation();
        if ($user->role === Role::Irban && ! $this->sandiOpen) {
            $this->ganti = false;
            $this->sandiOpen = true;

            return;
        }
        try {
            $action->handle($user, $this->only('password_lama', 'password', 'password_confirmation'), $this->diinput_oleh);
        } catch (ValidationException $e) {
            // Only a missing penginput keeps the modal open; field errors are shown on the form behind it.
            $this->sandiOpen = array_keys($e->errors()) === ['diinput_oleh'];
            throw $e;
        }
        $this->reset('password_lama', 'password', 'password_confirmation', 'sandiOpen', 'ganti');
        $this->dispatch('toast', type: 'success', message: 'Kata sandi diubah. Sesi lain dengan akun ini telah dikeluarkan.');
    }

    public function render()
    {
        $user = $this->pengguna();
        $irban = $user->role === Role::Irban;

        return view('livewire.profil', [
            'user' => $user,
            'irban' => $irban,
            'penginput' => $irban ? DiinputOleh::pilihan($user) : collect(),
            'maksMb' => (int) app(Settings::class)->get('upload.maks_mb'),
        ]);
    }
}
