<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Base of every SIMON-SPT notification: bell entry (database, written at once) + queued email, both only after commit. */
abstract class NotifikasiSimon extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /** Jenis whose email button reads "Buka SPT". */
    private const JENIS_SPT = [
        'pengajuan_baru', 'pengajuan_diterima', 'pengajuan_perlu_revisi', 'pengajuan_ditolak',
        'nomor_dibatalkan', 'spt_terlambat', 'pengingat_spt_final',
        'komentar_baru', 'minta_penjelasan', 'balasan_penjelasan',
    ];

    abstract public function jenis(): string;

    abstract public function judul(): string;

    abstract public function konteks(): ?string;

    /** Relative path, from `route(..., absolute: false)`. */
    abstract public function url(): string;

    /** @return list<string> Email body lines. */
    abstract public function isi(): array;

    public function catatan(): ?string
    {
        return null;
    }

    protected function subjek(): string
    {
        return $this->judul();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->routeNotificationFor('mail', $this) ? ['database', 'mail'] : ['database'];
    }

    /** @return array<string, string|null> */
    public function viaConnections(): array
    {
        return ['database' => 'sync', 'mail' => config('queue.default')];
    }

    /** @return array{jenis: string, judul: string, konteks: ?string, url: string, catatan: ?string} */
    public function toArray(object $notifiable): array
    {
        return [
            'jenis' => $this->jenis(),
            'judul' => $this->judul(),
            'konteks' => $this->konteks(),
            'url' => $this->url(),
            'catatan' => $this->catatan(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->pesanEmail();
    }

    /** The one email builder; $salam/$penutup are only set for pegawai emails (EmailPegawai). */
    public function pesanEmail(?string $salam = null, ?string $penutup = null): MailMessage
    {
        // Lines must come before action(): MailMessage files later lines as outro.
        return (new MailMessage)
            ->subject('[SIMON-SPT] '.$this->subjek())
            ->markdown('mail.notifikasi', ['catatan' => $this->catatan(), 'salam' => $salam, 'penutup' => $penutup])
            ->lines($this->isi())
            ->action($this->labelTombol(), url($this->url()));
    }

    /** User text in a Markdown email: backslash-escape Markdown punctuation so it never becomes links or formatting (< > & are left to HTML escaping). */
    public static function aman(string $t): string
    {
        return preg_replace('/[\\\\`*_{}\[\]()#+!|~:-]/', '\\\\$0', str_replace(["\r\n", "\n", "\r"], ' ', $t));
    }

    /** Inverse of aman(): for the plain-text part, where Markdown is not rendered. Keep the character class identical. */
    public static function tanpaEscape(string $t): string
    {
        return preg_replace('/\\\\([\\\\`*_{}\[\]()#+!|~:-])/', '$1', $t);
    }

    protected function labelTombol(): string
    {
        return in_array($this->jenis(), self::JENIS_SPT, true) ? 'Buka SPT' : 'Buka SIMON-SPT';
    }
}
