<?php

namespace App\Models;

use App\Enums\StatusRevisi;
use Database\Factories\PkptItemRevisiFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pkpt_item_id', 'tanggal_mulai_semula', 'tanggal_selesai_semula', 'tanggal_mulai', 'tanggal_selesai', 'alasan', 'diajukan_oleh', 'diinput_oleh'])]
class PkptItemRevisi extends Model
{
    /** @use HasFactory<PkptItemRevisiFactory> */
    use HasFactory;

    protected $table = 'pkpt_item_revisi';

    protected function casts(): array
    {
        return [
            'status' => StatusRevisi::class,
            'tanggal_mulai_semula' => 'immutable_date',
            'tanggal_selesai_semula' => 'immutable_date',
            'tanggal_mulai' => 'immutable_date',
            'tanggal_selesai' => 'immutable_date',
            'diputuskan_pada' => 'datetime',
        ];
    }

    public function item()
    {
        return $this->belongsTo(PkptItem::class, 'pkpt_item_id');
    }

    public function diajukanOleh()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }

    public function diputuskanOleh()
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }
}
