<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['user_id', 'jenis', 'format', 'filter', 'nama_file', 'path', 'status'])]
class Ekspor extends Model
{
    use HasUuids;

    protected $table = 'ekspor';

    protected function casts(): array
    {
        return ['filter' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
