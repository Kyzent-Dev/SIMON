<?php

namespace App\Models;

use Database\Factories\PegawaiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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
        return DB::table('tim_anggota')->where('pegawai_id', $this->id)->exists()
            || DB::table('irban_anggota')->where('pegawai_id', $this->id)->exists()
            || DB::table('irban_tahunan')->where('ppj_pegawai_id', $this->id)->exists();
    }
}
