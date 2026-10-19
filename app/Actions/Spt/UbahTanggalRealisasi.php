<?php

namespace App\Actions\Spt;

use App\Actions\Spt\Concerns\MelaksanakanSpt;
use App\Enums\KejadianSpt;
use App\Models\Spt;
use App\Models\User;
use App\Support\DiinputOleh;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Irban changes the realization dates of an issued SPT, with a reason (PRD §9.2, §9.3).
 *
 * handle(User, Spt, array{tanggal_mulai, tanggal_selesai (Y-m-d), alasan, diinput_oleh}): Spt
 */
class UbahTanggalRealisasi
{
    use MelaksanakanSpt;

    public function handle(User $user, Spt $spt, array $data): Spt
    {
        $this->izinkan($user, $spt);
        $e = [];
        $diinput = $this->penginput($user, $data, $e);

        $mulai = $this->tanggal($data['tanggal_mulai'] ?? null);
        $selesai = $this->tanggal($data['tanggal_selesai'] ?? null);
        if (! $mulai) {
            $e['tanggal_mulai'] = 'Isi tanggal mulai.';
        }
        if (! $selesai) {
            $e['tanggal_selesai'] = 'Isi tanggal selesai.';
        }
        if ($mulai && $selesai && $selesai->lt($mulai)) {
            $e['tanggal_selesai'] = 'Tanggal selesai tidak boleh sebelum tanggal mulai.';
        }
        $alasan = trim((string) ($data['alasan'] ?? ''));
        if (mb_strlen($alasan) < 10) {
            $e['alasan'] = 'Alasan minimal 10 karakter.';
        } elseif (mb_strlen($alasan) > 1000) {
            $e['alasan'] = 'Alasan maksimal 1000 karakter.';
        }
        if ($e) {
            throw ValidationException::withMessages($e);
        }

        $spt = DB::transaction(function () use ($user, $spt, $mulai, $selesai, $alasan, $diinput) {
            $spt = $this->kunci($user, $spt);
            $lama = ['tanggal_mulai' => $spt->tanggal_mulai?->toDateString(), 'tanggal_selesai' => $spt->tanggal_selesai?->toDateString()];
            $baru = ['tanggal_mulai' => $mulai->toDateString(), 'tanggal_selesai' => $selesai->toDateString()];
            if ($lama === $baru) {
                throw ValidationException::withMessages(['tanggal_mulai' => 'Tanggal realisasi tidak berubah.']);
            }
            $spt->fill($baru)->save();
            $spt->riwayat()->create([
                'kejadian' => KejadianSpt::TANGGAL_DIUBAH, 'nilai_lama' => $lama, 'nilai_baru' => $baru, 'alasan' => $alasan,
                'oleh' => $user->id, 'diinput_oleh' => $diinput,
            ]);

            return $spt;
        });
        DiinputOleh::ingat($user, $diinput);

        return $spt;
    }

    private function tanggal(mixed $v): ?CarbonImmutable
    {
        return is_string($v) && CarbonImmutable::canBeCreatedFromFormat($v, 'Y-m-d') ? CarbonImmutable::createFromFormat('Y-m-d', $v)->startOfDay() : null;
    }
}
