<?php

namespace App\Actions\Profil;

use App\Models\User;
use App\Services\Settings;
use App\Support\DiinputOleh;
use App\Support\GambarProfil;
use App\Support\GambarTidakTerbaca;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Profile photo or background (`jenis` foto|latar). The old file is deleted only after the new one is stored and saved. Irban: "Diinput oleh" required and logged. */
class SimpanGambarProfil
{
    public static function kolom(string $jenis): string
    {
        return match ($jenis) {
            'foto' => 'foto_profil',
            'latar' => 'latar_profil',
        };
    }

    public static function rules(string $jenis): array
    {
        return GambarProfil::rules($jenis, (int) app(Settings::class)->get('upload.maks_mb'));
    }

    public function handle(User $user, string $jenis, mixed $file, mixed $diinputOleh = null): void
    {
        $kolom = self::kolom($jenis);
        Validator::make([$jenis => $file], [$jenis => self::rules($jenis)])->validate();
        $penginput = DiinputOleh::wajib($user, $diinputOleh);
        DiinputOleh::catat($penginput);

        try {
            $baru = GambarProfil::simpan($file, $jenis);
        } catch (GambarTidakTerbaca) {
            // GD could not decode a file that passed the header checks (e.g. truncated); nothing was written.
            throw ValidationException::withMessages([$jenis => GambarProfil::TIDAK_TERBACA]);
        } catch (RuntimeException $e) {
            report($e); // disk/permission errors must reach the log
            throw ValidationException::withMessages([$jenis => 'Gambar gagal disimpan. Coba lagi.']);
        }
        $lama = $user->{$kolom};
        try {
            $user->forceFill([$kolom => $baru])->save();
        } catch (Throwable $e) {
            GambarProfil::hapus($baru);
            throw $e;
        }
        GambarProfil::hapus($lama);

        if ($penginput) {
            DiinputOleh::ingat($user, $penginput);
        }
        activity()->performedOn($user)->causedBy($user)->event("profil.{$jenis}")->log("profil.{$jenis}");
    }
}
