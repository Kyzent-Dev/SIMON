<?php

namespace App\Models;

use App\Enums\JenisObjek;
use Database\Factories\ObjekFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'jenis', 'parent_id'])]
class Objek extends Model
{
    /** @use HasFactory<ObjekFactory> */
    use HasFactory;

    protected $table = 'objek';

    protected function casts(): array
    {
        return ['jenis' => JenisObjek::class];
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('jenis')->orderBy('nama');
    }

    public function wilayah()
    {
        return $this->hasMany(IrbanWilayah::class);
    }
}
