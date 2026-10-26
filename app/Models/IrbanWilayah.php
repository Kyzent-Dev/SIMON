<?php

namespace App\Models;

use App\Models\Concerns\DicatatAktivitas;
use App\Models\Concerns\MenyegarkanCachePublik;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['irban_id', 'tahun', 'objek_id'])]
class IrbanWilayah extends Model
{
    use DicatatAktivitas, MenyegarkanCachePublik;

    protected $table = 'irban_wilayah';

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    public function objek()
    {
        return $this->belongsTo(Objek::class);
    }
}
