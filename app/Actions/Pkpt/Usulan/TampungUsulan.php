<?php

namespace App\Actions\Pkpt\Usulan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Actions\Pkpt\Perubahan\SimpanOperasi;
use App\Enums\AksiPerubahan;
use App\Enums\AksiUsulan;
use App\Enums\StatusUsulan;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\PkptPerubahanItem;
use App\Models\PkptUsulan;
use App\Models\User;
use App\Services\PerubahanPkpt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Admin takes an open usulan into a draf/ditolak package as a prefilled operation: geser → ubah with the new dates, hapus → hapus,
 * tambah → tambah, lainnya → ubah holding the item's current values (Admin edits it; until then cek() reports "Tidak ada yang berubah").
 */
class TampungUsulan
{
    use MengelolaPerubahan;

    public const TIDAK_TERBUKA = 'Usulan ini sudah ditampung, diterapkan, atau ditutup.';

    public function __construct(private SimpanOperasi $simpan) {}

    public function handle(User $user, PkptUsulan $u, PkptPerubahan $p): PkptPerubahanItem
    {
        Gate::forUser($user)->authorize('tampung', $u);
        Gate::forUser($user)->authorize('update', $p);
        abort_if($u->pkpt_id !== $p->pkpt_id, 404);

        return DB::transaction(function () use ($user, $u, $p) {
            // Package first, like HapusOperasi, so the two never lock in opposite order.
            $p = $this->kunciTerbuka($p);
            $u = PkptUsulan::whereKey($u->id)->lockForUpdate()->first();
            if ($u?->status !== StatusUsulan::Terbuka) {
                throw ValidationException::withMessages(['usulan' => self::TIDAK_TERBUKA]);
            }
            $tgl = fn ($d) => $d?->format('Y-m-d');
            $op = match ($u->aksi_usulan) {
                AksiUsulan::Geser => $this->simpan->handle($user, $p, null, ['aksi' => 'ubah', 'pkpt_item_id' => $u->pkpt_item_id,
                    'tanggal_mulai' => $tgl($u->tanggal_mulai), 'tanggal_selesai' => $tgl($u->tanggal_selesai)]),
                AksiUsulan::Hapus => $this->simpan->handle($user, $p, null, ['aksi' => 'hapus', 'pkpt_item_id' => $u->pkpt_item_id]),
                AksiUsulan::Tambah => $this->simpan->handle($user, $p, null, ['aksi' => 'tambah', 'irban_id' => $u->irban_id,
                    'jenis_penugasan_id' => $u->jenis_penugasan_id, 'tanggal_mulai' => $tgl($u->tanggal_mulai), 'tanggal_selesai' => $tgl($u->tanggal_selesai)]),
                AksiUsulan::Lainnya => $this->lainnya($p, $u),
            };
            $op->forceFill(['usulan_id' => $u->id])->save();
            $u->forceFill(['status' => StatusUsulan::Ditampung, 'perubahan_id' => $p->id])->save();

            return $op;
        });
    }

    /** SimpanOperasi rejects an unchanged `ubah`, so the placeholder is written directly with the same item checks. */
    private function lainnya(PkptPerubahan $p, PkptUsulan $u): PkptPerubahanItem
    {
        $item = PkptItem::where('pkpt_id', $p->pkpt_id)->aktif()->find((int) $u->pkpt_item_id);
        if (! $item) {
            throw ValidationException::withMessages(['pkpt_item_id' => PerubahanPkpt::ITEM_TIDAK_ADA]);
        }
        if ($p->operasi()->where('pkpt_item_id', $item->id)->exists()) {
            throw ValidationException::withMessages(['pkpt_item_id' => PerubahanPkpt::ITEM_GANDA]);
        }

        return PkptPerubahanItem::create(['perubahan_id' => $p->id, 'aksi' => AksiPerubahan::Ubah, 'pkpt_item_id' => $item->id,
            'data_baru' => PerubahanPkpt::data($item), 'urutan' => (int) $p->operasi()->max('urutan') + 1]);
    }
}
