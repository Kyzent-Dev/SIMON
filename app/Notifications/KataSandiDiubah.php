<?php

namespace App\Notifications;

/** Mail only, to the account's own address(es): its password was changed on the Profil page. Irban accounts name the pegawai who did it. */
class KataSandiDiubah extends NotifikasiSintas
{
    protected string $username;

    protected ?string $penginput;

    public function __construct(string $username, ?string $penginput = null)
    {
        $this->username = $username;
        $this->penginput = $penginput;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function jenis(): string
    {
        return 'kata_sandi_diubah';
    }

    public function judul(): string
    {
        return 'Kata sandi diubah';
    }

    public function konteks(): ?string
    {
        return $this->username;
    }

    public function url(): string
    {
        return route('profil', absolute: false);
    }

    public function isi(): array
    {
        return array_values(array_filter([
            'Kata sandi akun SINTAS '.self::aman($this->username).' baru saja diubah. Semua sesi lain dengan akun ini telah dikeluarkan.',
            $this->penginput ? 'Diinput oleh '.self::aman($this->penginput).'.' : null,
            'Jika Anda tidak melakukan perubahan ini, segera hubungi Admin.',
        ]));
    }
}
