<?php

namespace App\Notifications;

/** Mail only, to the requested new address: signed link (60 minutes) that swaps the account email. */
class VerifikasiEmailBaru extends NotifikasiSintas
{
    protected string $username;

    protected string $tautan;

    public function __construct(string $username, string $tautan)
    {
        $this->username = $username;
        $this->tautan = $tautan;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function jenis(): string
    {
        return 'verifikasi_email';
    }

    public function judul(): string
    {
        return 'Verifikasi email baru';
    }

    public function konteks(): ?string
    {
        return $this->username;
    }

    /** Absolute signed URL: this notification is never stored in the bell. */
    public function url(): string
    {
        return $this->tautan;
    }

    public function isi(): array
    {
        return [
            'Alamat email ini diminta menjadi email akun SINTAS '.self::aman($this->username).'. Klik tombol di bawah untuk mengonfirmasi. Tautan berlaku 60 menit dan Anda harus masuk dengan akun tersebut.',
            'Jika Anda tidak meminta perubahan ini, abaikan email ini. Email akun tidak berubah.',
        ];
    }

    protected function labelTombol(): string
    {
        return 'Verifikasi email';
    }
}
