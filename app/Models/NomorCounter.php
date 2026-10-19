<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** One numbering register (PRD §9.1): key (tahun, scope); only ever incremented by Penomoran or set by AturNomorAwal. */
#[Fillable(['tahun', 'scope'])]
class NomorCounter extends Model
{
    protected $table = 'nomor_counter';

    protected function casts(): array
    {
        return ['diatur_pada' => 'datetime'];
    }

    public function diaturOleh()
    {
        return $this->belongsTo(User::class, 'diatur_oleh');
    }
}
