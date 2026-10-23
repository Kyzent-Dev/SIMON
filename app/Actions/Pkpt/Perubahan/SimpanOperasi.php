<?php

namespace App\Actions\Pkpt\Perubahan;

use App\Actions\Pkpt\Perubahan\Concerns\MengelolaPerubahan;
use App\Actions\Pkpt\SavePkptItem;
use App\Enums\AksiPerubahan;
use App\Models\PkptItem;
use App\Models\PkptPerubahan;
use App\Models\PkptPerubahanItem;
use App\Models\User;
use App\Services\PerubahanPkpt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds or edits one operation of a draf/ditolak package.
 * $data: aksi (tambah|ubah|hapus), pkpt_item_id (ubah/hapus), irban_id, jenis_penugasan_id, tanggal_mulai, tanggal_selesai, keterangan, target_spt.
 * For `ubah`, fields left out are taken from the item. Same messages as PerubahanPkpt::cek().
 */
class SimpanOperasi
{
    use MengelolaPerubahan;

    private const KOLOM = ['irban_id', 'jenis_penugasan_id', 'tanggal_mulai', 'tanggal_selesai', 'keterangan', 'target_spt'];

    public function __construct(private PerubahanPkpt $perubahan) {}

    public function handle(User $user, PkptPerubahan $p, ?PkptPerubahanItem $op, array $data): PkptPerubahanItem
    {
        Gate::forUser($user)->authorize('update', $p);
        abort_if($op?->exists && $op->perubahan_id !== $p->id, 404);
        $aksi = AksiPerubahan::tryFrom((string) ($data['aksi'] ?? ''));
        if (! $aksi) {
            throw ValidationException::withMessages(['aksi' => 'Pilih aksi.']);
        }

        return DB::transaction(function () use ($p, $op, $data, $aksi) {
            $p = $this->kunciTerbuka($p);
            $pkpt = $p->pkpt()->first();
            $item = null;
            if ($aksi !== AksiPerubahan::Tambah) {
                $item = PkptItem::where('pkpt_id', $p->pkpt_id)->aktif()->find(filter_var($data['pkpt_item_id'] ?? null, FILTER_VALIDATE_INT) ?: 0);
                if (! $item) {
                    throw ValidationException::withMessages(['pkpt_item_id' => 'Pilih item.']);
                }
                if ($p->operasi()->where('pkpt_item_id', $item->id)->when($op?->exists, fn ($q) => $q->whereKeyNot($op->id))->exists()) {
                    throw ValidationException::withMessages(['pkpt_item_id' => PerubahanPkpt::ITEM_GANDA]);
                }
            }
            $isi = array_intersect_key($data, array_flip(self::KOLOM));
            if ($aksi === AksiPerubahan::Ubah) {
                $isi += PerubahanPkpt::data($item);
            }
            $galat = $this->perubahan->galat($pkpt, $aksi, $item, $isi, $item && $this->perubahan->punyaSpt([$item->id])[$item->id]);
            if ($galat) {
                throw ValidationException::withMessages($galat);
            }

            $op ??= new PkptPerubahanItem(['perubahan_id' => $p->id, 'urutan' => (int) $p->operasi()->max('urutan') + 1]);
            $op->fill([
                'aksi' => $aksi, 'pkpt_item_id' => $item?->id,
                'data_baru' => $aksi === AksiPerubahan::Hapus ? [] : PerubahanPkpt::bentuk(SavePkptItem::validated($pkpt, $isi)),
            ])->save();

            return $op;
        });
    }
}
