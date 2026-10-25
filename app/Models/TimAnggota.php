<?php

namespace App\Models;

use App\Enums\PeranTim;
use App\Models\Concerns\DicatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tim_id', 'pegawai_id', 'peran_default', 'urutan'])]
class TimAnggota extends Model
{
    use DicatatAktivitas;

    protected $table = 'tim_anggota';

    protected function casts(): array
    {
        return ['peran_default' => PeranTim::class];
    }

    public function tim()
    {
        return $this->belongsTo(Tim::class);
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }
}
