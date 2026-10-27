<?php

namespace App\Actions\Konten;

use App\Models\LandingSlide;
use App\Support\GambarSlide;
use App\Support\GambarTidakTerbaca;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Create or update a hero slide. New slides go to the end (`urutan = max + 1`); a new image replaces (and deletes) the old files. */
class SimpanSlide
{
    public static function rules(bool $baru): array
    {
        return [
            'judul' => ['required', 'string', 'max:80'],
            'kutipan' => ['nullable', 'string', 'max:200'],
            'pemilik_nama' => ['nullable', 'string', 'max:100'],
            'pemilik_jabatan' => ['nullable', 'string', 'max:100'],
            'alt' => ['nullable', 'string', 'max:150', 'required_unless:dekoratif,true'],
            'dekoratif' => ['boolean'],
            'aktif' => ['boolean'],
            // bail: one message per file. The readable/6000 px check runs before GD ever decodes it (a tiny 20000 x 20000 PNG would need > 1 GB).
            'gambar' => [$baru ? 'required' : 'nullable', 'bail', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120', self::terbaca(...)],
        ];
    }

    public const LEBAR_MINIMAL = 'Lebar gambar minimal 960 piksel.';

    public const TIDAK_TERBACA = 'Gambar tidak dapat dibaca. Unggah ulang file lain.';

    /** Header must be readable, at most 6000 x 6000 px and at least 960 px wide once upright. */
    private static function terbaca(string $attribute, mixed $value, \Closure $fail): void
    {
        $info = $value instanceof UploadedFile ? @getimagesize($value->getRealPath()) : false;
        if (! $info) {
            $fail(self::TIDAK_TERBACA);
        } elseif ($info[0] > 6000 || $info[1] > 6000) {
            $fail('Ukuran gambar maksimal 6000 × 6000 piksel.');
        } elseif (GambarSlide::ukuranTegak($info[0], $info[1], GambarSlide::sudutExif($value))[0] < 960) {
            // Measured after the EXIF rotation, as the image will be stored: a portrait phone photo is stored sideways.
            $fail(self::LEBAR_MINIMAL);
        }
    }

    public static function messages(): array
    {
        return [
            'judul.required' => 'Judul wajib diisi.',
            'alt.required_unless' => 'Teks alternatif gambar wajib diisi, atau tandai gambar sebagai dekoratif.',
            'gambar.required' => 'Gambar wajib diunggah.',
            'gambar.mimes' => 'Gambar harus JPG, PNG, atau WebP.',
        ];
    }

    /** @param array{judul:string, kutipan:?string, pemilik_nama:?string, pemilik_jabatan:?string, alt:?string, dekoratif:bool, aktif:bool} $data validated */
    public function handle(?LandingSlide $slide, array $data, ?UploadedFile $gambar): LandingSlide
    {
        $data = array_map(function ($v) {
            $v = is_string($v) ? trim($v) : $v;

            return $v === '' ? null : $v;
        }, $data);
        if ($data['dekoratif']) {
            $data['alt'] = null;
        }
        try {
            $baru = $gambar ? GambarSlide::simpan($gambar) : null;
        } catch (GambarTidakTerbaca) {
            // GD could not decode a file that passed the header checks (e.g. truncated); GambarSlide left no files. Other RuntimeExceptions (storage write) stay errors.
            throw ValidationException::withMessages(['gambar' => self::TIDAK_TERBACA]);
        }
        $lama = $baru && $slide ? $slide->gambar : null;

        try {
            $slide = DB::transaction(function () use ($slide, $data, $baru) {
                $slide ??= new LandingSlide(['urutan' => (int) LandingSlide::lockForUpdate()->max('urutan') + 1]);
                $slide->fill($data + array_filter(['gambar' => $baru]))->save();

                return $slide;
            });
        } catch (Throwable $e) {
            GambarSlide::hapus($baru);
            throw $e;
        }
        GambarSlide::hapus($lama);

        return $slide;
    }
}
