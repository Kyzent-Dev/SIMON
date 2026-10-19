<?php

namespace App\Actions\Pkpt;

use App\Enums\Role;
use App\Enums\StatusPkpt;
use App\Enums\StatusRevisi;
use App\Models\PkptItem;
use App\Models\PkptItemRevisi;
use App\Models\User;
use App\Services\KesesuaianPkpt;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AjukanRevisiJadwal
{
    public function __construct(private KesesuaianPkpt $kesesuaian) {}

    /** $data: tanggal_mulai, tanggal_selesai, alasan, diinput_oleh (pegawai id; required for Irban accounts). */
    public function handle(User $user, PkptItem $item, array $data): PkptItemRevisi
    {
        $pkpt = $item->pkpt;
        // Checked before the policy (which also requires Disahkan) so the irban sees the reason instead of a 403.
        if ($pkpt->status !== StatusPkpt::Disahkan) {
            throw ValidationException::withMessages(['pkpt' => 'Revisi hanya untuk PKPT yang sudah disahkan.']);
        }
        Gate::forUser($user)->authorize('create', [PkptItemRevisi::class, $item]);

        $errors = [];
        $dates = SavePkptItem::tanggal($pkpt, $data, $errors);
        $alasan = trim((string) ($data['alasan'] ?? ''));
        if ($alasan === '') {
            $errors['alasan'] = 'Isi alasan revisi.';
        } elseif (mb_strlen($alasan) > 1000) {
            $errors['alasan'] = 'Alasan revisi maksimal 1000 karakter.';
        }
        $diinput = filter_var($data['diinput_oleh'] ?? null, FILTER_VALIDATE_INT) ?: null;
        if ($user->role === Role::Irban) {
            if (! $diinput) {
                $errors['diinput_oleh'] = 'Pilih nama penginput terlebih dahulu';
            } elseif (! DiinputOleh::pilihan($user)->contains('id', $diinput)) {
                $errors['diinput_oleh'] = 'Penginput harus PPJ atau anggota aktif Irban ini tahun '.DiinputOleh::tahun().'.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($user, $item, $dates, $alasan, $diinput) {
            // The item row lock serializes concurrent submits, so only one pending revision can exist.
            PkptItem::whereKey($item->id)->lockForUpdate()->first();
            if ($item->revisi()->where('status', StatusRevisi::Menunggu)->exists()) {
                throw ValidationException::withMessages(['revisi' => 'Item ini masih punya revisi yang menunggu persetujuan.']);
            }
            [$mulai, $selesai] = $this->kesesuaian->rentang($item);
            if ($mulai->equalTo($dates['tanggal_mulai']) && $selesai->equalTo($dates['tanggal_selesai'])) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'Tanggal baru sama dengan jadwal yang berlaku.']);
            }

            return PkptItemRevisi::create([
                'pkpt_item_id' => $item->id,
                'tanggal_mulai_semula' => $mulai, 'tanggal_selesai_semula' => $selesai,
                'tanggal_mulai' => $dates['tanggal_mulai'], 'tanggal_selesai' => $dates['tanggal_selesai'],
                'alasan' => $alasan, 'diajukan_oleh' => $user->id, 'diinput_oleh' => $diinput,
            ]);
        });
    }
}
