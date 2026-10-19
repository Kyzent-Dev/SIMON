<?php

namespace App\Models;

use Database\Factories\SptKendalaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['spt_id', 'kategori', 'uraian', 'diinput_oleh', 'dibuat_oleh'])]
class SptKendala extends Model
{
    /** @use HasFactory<SptKendalaFactory> */
    use HasFactory;

    protected $table = 'spt_kendala';

    public function spt()
    {
        return $this->belongsTo(Spt::class);
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }
}
