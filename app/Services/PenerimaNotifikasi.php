<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Komentar;
use App\Models\Pegawai;
use App\Models\PkptPerubahan;
use App\Models\Spt;
use App\Models\User;
use App\Notifications\EmailPegawai;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

/** The only place that decides who receives a notification (Fase 6). */
class PenerimaNotifikasi
{
    public function __construct(private PerubahanPkpt $perubahan) {}

    /** @return Collection<int, User> */
    public function bagianUmum(): Collection
    {
        return User::where('role', Role::BagianUmum)->get();
    }

    /** @return Collection<int, User> */
    public function admin(): Collection
    {
        return User::where('role', Role::Admin)->get();
    }

    /** The Admin who created the package, or every Admin when that account is gone or no longer Admin. @return Collection<int, User> */
    public function pembuatPerubahan(PkptPerubahan $p): Collection
    {
        $pembuat = $p->pembuat()->first();

        return $pembuat?->role === Role::Admin ? User::whereKey($pembuat->id)->get() : $this->admin();
    }

    /** Approved package: its creator, the other Pimpinan, and the Irban accounts of every affected irban. @return Collection<int, User> */
    public function perubahanDisetujui(PkptPerubahan $p, User $pemutus): Collection
    {
        $irban = User::where('role', Role::Irban)->whereIn('irban_id', $this->perubahan->irbanTerdampak($p))->get();

        return $this->pembuatPerubahan($p)->merge($this->pimpinan())->merge($irban)
            ->reject(fn (User $u) => $u->id === $pemutus->id)->unique('id')->values();
    }

    /** @return Collection<int, User> */
    public function pimpinan(): Collection
    {
        return User::where('role', Role::Pimpinan)->get();
    }

    /**
     * Parties of a comment thread: the SPT's irban users and every author in the thread, minus the author of $komentar. One query.
     *
     * @return Collection<int, User>
     */
    public function utasKomentar(Komentar $komentar): Collection
    {
        $root = $komentar->parent_id ?? $komentar->id;
        $penulis = Komentar::select('user_id')->where(fn ($q) => $q->whereKey($root)->orWhere('parent_id', $root));
        $irban = $this->akunIrban(Spt::select('irban_id')->whereKey($komentar->spt_id))->select('id');

        return User::whereKeyNot($komentar->user_id)
            ->where(fn ($q) => $q->whereIn('id', $irban)->orWhereIn('id', $penulis))
            ->get();
    }

    /** Only the requester of an export. @return Collection<int, User> */
    public function pengguna(User $user): Collection
    {
        return User::whereKey($user->id)->get();
    }

    /** The pegawai's own address, unless inactive, empty, or already covered by an account address that got the same notification. */
    public function emailPegawai(?Pegawai $p, Collection $akun): ?string
    {
        if (! $p?->aktif || blank($p->email)) {
            return null;
        }
        $sudah = $akun->flatMap(fn (User $u) => $u->routeNotificationForMail() ?? [])->map(fn ($a) => mb_strtolower($a));

        return $sudah->contains(mb_strtolower($p->email)) ? null : $p->email;
    }

    /** Sends to the irban's accounts and, unless covered, the pegawai's own address. $buat makes a fresh notification per send. Call after commit. */
    public function kirimKeIrban(int $irbanId, ?Pegawai $diinputOleh, Closure $buat): void
    {
        $akun = $this->irban($irbanId);
        Notification::send($akun, $buat());
        if ($email = $this->emailPegawai($diinputOleh, $akun)) {
            Notification::route('mail', $email)->notify(new EmailPegawai($buat(), $diinputOleh->nama_bergelar));
        }
    }

    /** @return Collection<int, User> */
    public function irban(int $irbanId): Collection
    {
        return $this->akunIrban($irbanId)->get();
    }

    /** The accounts of an irban, the one rule irban() and utasKomentar() share; $irbanId may be a subquery. */
    private function akunIrban(int|Builder $irbanId): Builder
    {
        return User::where('role', Role::Irban)->where('irban_id', $irbanId);
    }
}
