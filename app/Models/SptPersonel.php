<?php

namespace App\Models;

use App\Enums\PeranTim;
use App\Models\Concerns\DicatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['spt_id', 'pegawai_id', 'peran', 'urutan', 'sumber', 'tim_id'])]
class SptPersonel extends Model
{
    use DicatatAktivitas;

    protected $table = 'spt_personel';

    protected function casts(): array
    {
        return ['peran' => PeranTim::class];
    }

    public function spt()
    {
        return $this->belongsTo(Spt::class);
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function tim()
    {
        return $this->belongsTo(Tim::class);
    }
}
