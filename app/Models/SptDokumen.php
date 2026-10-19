<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['spt_id', 'jenis', 'path', 'nama_asli', 'ukuran', 'sha256', 'diunggah_oleh', 'diinput_oleh'])]
class SptDokumen extends Model
{
    protected $table = 'spt_dokumen';

    public function spt()
    {
        return $this->belongsTo(Spt::class);
    }

    public function diunggahOleh()
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }
}
