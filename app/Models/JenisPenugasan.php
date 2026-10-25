<?php

namespace App\Models;

use App\Models\Concerns\DicatatAktivitas;
use Database\Factories\JenisPenugasanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'warna', 'warna_teks'])]
class JenisPenugasan extends Model
{
    /** @use HasFactory<JenisPenugasanFactory> */
    use DicatatAktivitas, HasFactory;

    protected $table = 'jenis_penugasan';

    /** True when a pkpt_item or spt points at this type, so it cannot be deleted. */
    public function isReferenced(): bool
    {
        return PkptItem::where('jenis_penugasan_id', $this->id)->exists() || Spt::where('jenis_penugasan_id', $this->id)->exists();
    }
}
