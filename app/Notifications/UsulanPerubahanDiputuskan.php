<?php

namespace App\Notifications;

use App\Enums\AksiUsulan;
use App\Enums\StatusUsulan;
use App\Models\PkptUsulan;
use App\Services\PenerimaNotifikasi;
use App\Support\Tanggal;
use Illuminate\Support\Facades\Notification;

/** An usulan was applied (its package approved) or closed by Admin: the irban accounts + the pegawai who entered it (E5, Task 1 K9). */
class UsulanPerubahanDiputuskan extends NotifikasiSimon
{
    protected bool $diterapkan;

    protected int $tahun;

    protected ?int $nomor;

    protected ?string $konteks;

    protected ?string $catatanPenutupan;

    public function __construct(PkptUsulan $u)
    {
        $this->diterapkan = $u->status === StatusUsulan::Diterapkan;
        $this->tahun = (int) $u->pkpt->tahun;
        $this->nomor = $u->perubahan?->nomor_urut;
        $this->konteks = self::konteksUsulan($u);
        $this->catatanPenutupan = $u->catatan_penutupan;
    }

    /** Sends to the irban accounts and, unless already covered, the "Diinput oleh" pegawai's own address. Call after commit. */
    public static function kirim(PkptUsulan $u): void
    {
        $penerima = app(PenerimaNotifikasi::class);
        $akun = $penerima->irban($u->irban_id);
        Notification::send($akun, new self($u));
        if ($email = $penerima->emailPegawai($u->diinputOleh, $akun)) {
            Notification::route('mail', $email)->notify(new EmailPegawai(new self($u), $u->diinputOleh->nama_bergelar));
        }
    }

    /** "Geser jadwal · Reviu · 1–20 April 2026": the proposed dates for geser/tambah, the item's for hapus/lainnya. */
    public static function konteksUsulan(PkptUsulan $u): ?string
    {
        $baru = in_array($u->aksi_usulan, [AksiUsulan::Geser, AksiUsulan::Tambah], true) && $u->tanggal_mulai && $u->tanggal_selesai;
        $rentang = $baru ? Tanggal::rentang($u->tanggal_mulai, $u->tanggal_selesai)
            : ($u->item ? Tanggal::rentang($u->item->tanggal_mulai, $u->item->tanggal_selesai) : null);

        return collect([$u->aksi_usulan->label(), ($u->jenisPenugasan ?? $u->item?->jenisPenugasan)?->nama, $rentang])->filter()->implode(' · ');
    }

    public function jenis(): string
    {
        return $this->diterapkan ? 'usulan_perubahan_diterapkan' : 'usulan_perubahan_ditutup';
    }

    public function judul(): string
    {
        return $this->diterapkan ? 'Usulan perubahan diterapkan' : 'Usulan perubahan ditutup';
    }

    public function konteks(): ?string
    {
        return $this->konteks;
    }

    public function url(): string
    {
        return route('irban.pkpt', ['tahun' => $this->tahun, 'tampilan' => 'usulan'], false);
    }

    public function catatan(): ?string
    {
        return $this->diterapkan ? null : $this->catatanPenutupan;
    }

    public function isi(): array
    {
        return [$this->diterapkan
            ? 'Usulan perubahan Anda sudah diterapkan'.($this->nomor ? " dalam Perubahan ke-{$this->nomor}" : '')." PKPT {$this->tahun}. Jadwal baru sudah berlaku."
            : "Admin menutup usulan perubahan Anda untuk PKPT {$this->tahun} tanpa menerapkannya."];
    }
}
