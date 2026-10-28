<?php

namespace App\Actions\Konten;

use App\Services\Settings;
use App\Support\GambarSlide;
use App\Support\GambarTidakTerbaca;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/** The one background photo of the login page's brand panel (setting `masuk.latar` = base path `login/{uuid}`, '' = plain panel). */
class AturLatarMasuk
{
    public const KUNCI = 'masuk.latar';

    public function __construct(private Settings $settings) {}

    /** Same checks as a slide image (type, 5 MB, readable, at most 6000 px, at least 960 px wide). */
    public static function rules(): array
    {
        return ['foto_latar' => ['required', ...SimpanSlide::rules(true)['gambar']]];
    }

    public static function messages(): array
    {
        return ['foto_latar.required' => 'Foto wajib dipilih.', 'foto_latar.mimes' => 'Foto harus JPG, PNG, atau WebP.'];
    }

    /** Store the photo, point the setting at it, then delete the old files (only after the commit). */
    public function ganti(UploadedFile $foto): void
    {
        try {
            $baru = GambarSlide::simpan($foto, 'login');
        } catch (GambarTidakTerbaca) {
            throw ValidationException::withMessages(['foto_latar' => SimpanSlide::TIDAK_TERBACA]);
        }
        $lama = (string) $this->settings->segar(self::KUNCI);
        try {
            DB::transaction(fn () => $this->settings->set(self::KUNCI, $baru));
        } catch (Throwable $e) {
            GambarSlide::hapus($baru);
            throw $e;
        }
        GambarSlide::hapus($lama);
    }

    public function hapus(): void
    {
        $lama = (string) $this->settings->segar(self::KUNCI);
        DB::transaction(fn () => $this->settings->set(self::KUNCI, ''));
        GambarSlide::hapus($lama);
    }

    /** Public URL of one variant (960 or 1920), or null when no photo is set. */
    public static function url(string $base, int $lebar): ?string
    {
        return $base === '' ? null : Storage::disk('public')->url("{$base}-{$lebar}.webp");
    }
}
