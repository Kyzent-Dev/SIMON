<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** `kejadian` holds an App\Enums\KejadianSpt constant. */
#[Fillable(['spt_id', 'kejadian', 'nilai_lama', 'nilai_baru', 'alasan', 'oleh', 'diinput_oleh'])]
class SptRiwayat extends Model
{
    protected $table = 'spt_riwayat';

    protected function casts(): array
    {
        return ['nilai_lama' => 'array', 'nilai_baru' => 'array'];
    }

    public function spt()
    {
        return $this->belongsTo(Spt::class);
    }

    public function olehUser()
    {
        return $this->belongsTo(User::class, 'oleh');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }
}
