<?php

namespace App\Models;

use App\Models\Concerns\DicatatAktivitas;
use Database\Factories\TimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['irban_id', 'tahun', 'nama'])]
class Tim extends Model
{
    /** @use HasFactory<TimFactory> */
    use DicatatAktivitas, HasFactory;

    protected $table = 'tim';

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    public function anggota()
    {
        return $this->hasMany(TimAnggota::class)->orderBy('urutan');
    }
}
