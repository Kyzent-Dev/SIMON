<?php

namespace App\Actions\Pkpt\Usulan;

use App\Actions\Pkpt\SavePkptItem;
use App\Enums\AksiUsulan;
use App\Enums\StatusItemPkpt;
use App\Enums\StatusPkpt;
use App\Models\JenisPenugasan;
use App\Models\Pkpt;
use App\Models\PkptItem;
use App\Models\PkptPerubahanItem;
use App\Models\PkptUsulan;
use App\Models\User;
use App\Notifications\UsulanPerubahanBaru;
use App\Services\PenerimaNotifikasi;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * An Irban proposes a change to its own active item (geser, hapus, lainnya) or a new item (tambah) of a Disahkan PKPT.
 * $data: aksi_usulan, tanggal_mulai, tanggal_selesai (geser/tambah), jenis_penugasan_id (tambah), alasan, diinput_oleh.
 */
class AjukanUsulan
{
    /** Stale tab: the item was this irban's but an approved Perubahan PKPT removed it or moved it to another irban (key `item`, a toast). */
    public const ITEM_BASI = 'Item ini sudah dihapus atau dipindahkan oleh Perubahan PKPT.';

    /**
     * Own active item of a Disahkan PKPT. An item that was this irban's (it still carries the irban but is dihapus, or an applied operation
     * recorded this irban in data_lama) throws ITEM_BASI; any other id is a 404, so foreign ids stay indistinguishable from missing ones.
     */
    public static function itemSendiri(User $user, int $id, ?int $pkptId = null): PkptItem
    {
        $item = PkptItem::whereHas('pkpt', fn ($q) => $q->where('status', StatusPkpt::Disahkan->value))
            ->when($pkptId, fn ($q) => $q->where('pkpt_id', $pkptId))->find($id);
        $irban = (int) $user->irban_id;
        if ($item && $item->irban_id === $irban && $item->status === StatusItemPkpt::Aktif) {
            return $item;
        }
        $pernah = $item && ($item->irban_id === $irban || PkptPerubahanItem::where('pkpt_item_id', $item->id)->whereNotNull('data_lama')->get()
            ->contains(fn (PkptPerubahanItem $op) => (int) ($op->data_lama['irban_id'] ?? 0) === $irban));
        abort_unless($pernah, 404);

        throw ValidationException::withMessages(['item' => self::ITEM_BASI]);
    }

    public function handle(User $user, Pkpt $pkpt, ?PkptItem $item, array $data): PkptUsulan
    {
        // Before the policy (which also requires Disahkan) so the irban sees the reason instead of a 403.
        if ($pkpt->status !== StatusPkpt::Disahkan) {
            throw ValidationException::withMessages(['pkpt' => 'Usulan perubahan hanya untuk PKPT yang sudah disahkan.']);
        }
        Gate::forUser($user)->authorize('create', [PkptUsulan::class, $pkpt]);

        $errors = [];
        $aksi = AksiUsulan::tryFrom((string) ($data['aksi_usulan'] ?? ''));
        if (! $aksi) {
            throw ValidationException::withMessages(['aksi_usulan' => 'Pilih jenis usulan.']);
        }
        if ($aksi === AksiUsulan::Tambah) {
            $item = null;
            $jenis = filter_var($data['jenis_penugasan_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
            if (! $jenis || ! JenisPenugasan::whereKey($jenis)->exists()) {
                $errors['jenis_penugasan_id'] = 'Pilih jenis penugasan.';
            }
        } else {
            if (! $item) {
                throw ValidationException::withMessages(['pkpt_item_id' => 'Pilih item PKPT.']);
            }
            $item = self::itemSendiri($user, $item->id, $pkpt->id);
        }

        $tanggal = in_array($aksi, [AksiUsulan::Geser, AksiUsulan::Tambah], true) ? SavePkptItem::tanggal($pkpt, $data, $errors) : [];
        if ($aksi === AksiUsulan::Geser && count($tanggal) === 2 && ! $errors
            && $item->tanggal_mulai->equalTo($tanggal['tanggal_mulai']) && $item->tanggal_selesai->equalTo($tanggal['tanggal_selesai'])) {
            $errors['tanggal_mulai'] = 'Jadwal baru sama dengan jadwal yang berlaku.';
        }
        $alasan = trim((string) ($data['alasan'] ?? ''));
        if ($alasan === '') {
            $errors['alasan'] = 'Isi alasan usulan.';
        } elseif (mb_strlen($alasan) > 1000) {
            $errors['alasan'] = 'Alasan usulan maksimal 1000 karakter.';
        }
        $diinput = filter_var($data['diinput_oleh'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($galat = DiinputOleh::galat($user, $diinput)) {
            $errors['diinput_oleh'] = $galat;
        } else {
            DiinputOleh::catat($diinput);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $u = PkptUsulan::create([
            'pkpt_id' => $pkpt->id, 'irban_id' => $user->irban_id, 'pkpt_item_id' => $item?->id, 'aksi_usulan' => $aksi,
            'tanggal_mulai' => $tanggal['tanggal_mulai'] ?? null, 'tanggal_selesai' => $tanggal['tanggal_selesai'] ?? null,
            'jenis_penugasan_id' => $aksi === AksiUsulan::Tambah ? $jenis : null,
            'alasan' => $alasan, 'diajukan_oleh' => $user->id, 'diinput_oleh' => $diinput,
        ]);
        DiinputOleh::ingat($user, $diinput);

        Notification::send(app(PenerimaNotifikasi::class)->admin(), new UsulanPerubahanBaru($u));

        return $u;
    }
}
