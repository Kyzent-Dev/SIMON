<?php

namespace App\Models;

use App\Enums\StatusRevisi;
use Database\Factories\PkptItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['pkpt_id', 'irban_id', 'jenis_penugasan_id', 'tanggal_mulai', 'tanggal_selesai', 'keterangan', 'target_spt'])]
class PkptItem extends Model
{
    /** @use HasFactory<PkptItemFactory> */
    use HasFactory;

    protected $table = 'pkpt_item';

    protected function casts(): array
    {
        return ['tanggal_mulai' => 'immutable_date', 'tanggal_selesai' => 'immutable_date'];
    }

    public function pkpt()
    {
        return $this->belongsTo(Pkpt::class);
    }

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    public function jenisPenugasan()
    {
        return $this->belongsTo(JenisPenugasan::class);
    }

    public function revisi()
    {
        return $this->hasMany(PkptItemRevisi::class);
    }

    /** Approved revisions, latest first (by diputuskan_pada, then id). */
    public function revisiDisetujui()
    {
        return $this->revisi()->where('status', StatusRevisi::Disetujui)->orderByDesc('diputuskan_pada')->orderByDesc('id');
    }

    /** Latest approved revision; queried fresh so it is never stale, unless a batch caller eager-loaded `revisiDisetujui`. */
    public function revisiDisetujuiTerakhir(): ?PkptItemRevisi
    {
        return $this->relationLoaded('revisiDisetujui') ? $this->revisiDisetujui->first() : $this->revisiDisetujui()->first();
    }
}
