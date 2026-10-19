<?php

namespace App\Actions\Spt;

use App\Actions\Spt\Concerns\MelaksanakanSpt;
use App\Enums\KejadianSpt;
use App\Models\Spt;
use App\Models\SptKendala;
use App\Models\User;
use App\Services\Settings;
use App\Support\DiinputOleh;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Irban fills in (or edits in place) the single kendala of an SPT.
 *
 * handle(User, Spt, array{kategori, uraian, diinput_oleh}): SptKendala
 */
class SimpanKendala
{
    use MelaksanakanSpt;

    public function __construct(private Settings $settings) {}

    public function handle(User $user, Spt $spt, array $data): SptKendala
    {
        $this->izinkan($user, $spt);
        $e = [];
        $diinput = $this->penginput($user, $data, $e);
        $kategori = is_string($data['kategori'] ?? null) ? $data['kategori'] : '';
        if (! in_array($kategori, (array) $this->settings->get('kendala.kategori'), true)) {
            $e['kategori'] = 'Pilih kategori kendala.';
        }
        $uraian = trim((string) ($data['uraian'] ?? ''));
        if (mb_strlen($uraian) < 10) {
            $e['uraian'] = 'Uraian kendala minimal 10 karakter.';
        } elseif (mb_strlen($uraian) > 2000) {
            $e['uraian'] = 'Uraian kendala maksimal 2000 karakter.';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        $kendala = DB::transaction(function () use ($user, $spt, $kategori, $uraian, $diinput) {
            $spt = $this->kunci($user, $spt);
            $kendala = $spt->kendala()->first() ?? new SptKendala(['spt_id' => $spt->id, 'dibuat_oleh' => $user->id]);
            $lama = $kendala->exists ? ['kategori' => $kendala->kategori, 'uraian' => $kendala->uraian] : null;
            $kendala->fill(['kategori' => $kategori, 'uraian' => $uraian, 'diinput_oleh' => $diinput])->save();
            $spt->riwayat()->create([
                'kejadian' => KejadianSpt::KENDALA_DIISI, 'nilai_lama' => $lama, 'nilai_baru' => ['kategori' => $kategori, 'uraian' => $uraian],
                'oleh' => $user->id, 'diinput_oleh' => $diinput,
            ]);

            return $kendala;
        });
        DiinputOleh::ingat($user, $diinput);

        return $kendala;
    }
}
