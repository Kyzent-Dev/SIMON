<?php

namespace App\Models;

use App\Enums\StatusPkpt;
use Database\Factories\PkptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tahun', 'nomor_sk', 'tanggal_sk'])]
class Pkpt extends Model
{
    /** @use HasFactory<PkptFactory> */
    use HasFactory;

    protected $table = 'pkpt';

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return [
            'status' => StatusPkpt::class,
            'tanggal_sk' => 'immutable_date',
            'disahkan_pada' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(PkptItem::class);
    }

    public function disahkanOleh()
    {
        return $this->belongsTo(User::class, 'disahkan_oleh');
    }

    public function isDraf(): bool
    {
        return $this->status === StatusPkpt::Draf;
    }
}
