<?php

namespace App\Models;

use Database\Factories\PegawaiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama_bergelar', 'nip', 'pangkat', 'golongan', 'jabatan', 'aktif'])]
class Pegawai extends Model
{
    /** @use HasFactory<PegawaiFactory> */
    use HasFactory;

    protected $table = 'pegawai';

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** True when other records point at this pegawai, so it may only be deactivated. */
    public function isReferenced(): bool
    {
        // shortcut: no references exist yet; Task 6/7 add irban_anggota/tim_anggota checks here.
        return false;
    }
}
