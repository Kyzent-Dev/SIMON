<?php

namespace App\Models;

use Database\Factories\TimFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['irban_id', 'tahun', 'nama'])]
class Tim extends Model
{
    /** @use HasFactory<TimFactory> */
    use HasFactory;

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
