<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Mail-only copy of a NotifikasiSimon for the pegawai who entered the data (sent to an on-demand route, no bell). */
class EmailPegawai extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public const PENUTUP = 'Anda menerima email ini karena tercatat sebagai penginput data di SIMON.';

    public function __construct(protected NotifikasiSimon $notifikasi, protected string $nama) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->notifikasi->pesanEmail("Yth. {$this->nama},", self::PENUTUP);
    }
}
