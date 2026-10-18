<?php

namespace App\Actions\MasterData;

use App\Enums\JenisObjek;
use App\Models\Objek;
use Illuminate\Validation\ValidationException;

class SaveObjek
{
    public function handle(?Objek $objek, string $nama, string $jenis, ?int $parentId): Objek
    {
        $nama = trim($nama);
        if ($nama === '' || mb_strlen($nama) > 255) {
            throw ValidationException::withMessages(['nama' => 'Nama wajib diisi (maksimal 255 karakter).']);
        }
        $type = JenisObjek::tryFrom($jenis) ?? throw ValidationException::withMessages(['jenis' => 'Pilih jenis yang valid.']);

        if ($type->needsParent()) {
            $parent = $parentId ? Objek::find($parentId) : null;
            if (! $parent || $parent->jenis !== JenisObjek::Kecamatan || $parent->id === $objek?->id) {
                throw ValidationException::withMessages(['parentId' => 'Desa, sekolah, puskesmas, dan BUMDes wajib punya induk kecamatan.']);
            }
        } elseif ($parentId !== null) {
            throw ValidationException::withMessages(['parentId' => 'Kecamatan, RSUD, Dinas/Badan, dan BUMD tidak boleh punya induk.']);
        }

        if ($objek && $objek->jenis === JenisObjek::Kecamatan && $type !== JenisObjek::Kecamatan && $objek->children()->exists()) {
            throw ValidationException::withMessages(['jenis' => 'Kecamatan yang masih punya objek di bawahnya tidak dapat diubah jenisnya.']);
        }

        if ($objek && ! in_array($type, JenisObjek::wilayahTypes(), true) && $objek->wilayah()->exists()) {
            throw ValidationException::withMessages(['jenis' => 'Objek yang sudah ditugaskan ke Irban tidak dapat diubah ke jenis yang tidak bisa ditugaskan.']);
        }

        $objek ??= new Objek;
        $objek->fill(['nama' => $nama, 'jenis' => $type->value, 'parent_id' => $parentId])->save();

        return $objek;
    }
}
