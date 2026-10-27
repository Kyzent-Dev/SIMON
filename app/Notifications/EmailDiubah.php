<?php

namespace App\Notifications;

/** Mail only, to the old address after a verified email change. */
class EmailDiubah extends NotifikasiSintas
{
    protected string $username;

    protected string $emailBaru;

    public function __construct(string $username, string $emailBaru)
    {
        $this->username = $username;
        $this->emailBaru = $emailBaru;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function jenis(): string
    {
        return 'email_diubah';
    }

    public function judul(): string
    {
        return 'Email akun diubah';
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
        return [
            'Email akun SINTAS '.self::aman($this->username).' telah diubah menjadi '.self::aman($this->emailBaru).'. Email ini tidak lagi menerima pemberitahuan untuk akun tersebut.',
            'Jika Anda tidak melakukan perubahan ini, segera hubungi Admin.',
        ];
    }
}
