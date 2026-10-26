<?php

namespace App\Models;

use App\Models\Concerns\DicatatAktivitas;
use App\Models\Concerns\MenyegarkanCachePublik;
use Database\Factories\IrbanTahunanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['irban_id', 'tahun', 'ppj_pegawai_id'])]
class IrbanTahunan extends Model
{
    /** @use HasFactory<IrbanTahunanFactory> */
    use DicatatAktivitas, HasFactory, MenyegarkanCachePublik;

    protected $table = 'irban_tahunan';

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    public function ppj()
    {
        return $this->belongsTo(Pegawai::class, 'ppj_pegawai_id');
    }

    public function anggota()
    {
        return $this->belongsToMany(Pegawai::class, 'irban_anggota', 'irban_tahunan_id', 'pegawai_id')->withTimestamps();
    }
}
