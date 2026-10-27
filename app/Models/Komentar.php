<?php

namespace App\Models;

use App\Enums\StatusPermintaan;
use App\Models\Concerns\DicatatAktivitas;
use Database\Factories\KomentarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Comments are immutable (no edit, no delete); status/closing columns are not fillable: TulisKomentar and TutupPermintaan forceFill them.
#[Fillable(['spt_id', 'parent_id', 'user_id', 'diinput_oleh', 'isi', 'minta_penjelasan'])]
class Komentar extends Model
{
    /** @use HasFactory<KomentarFactory> */
    use DicatatAktivitas, HasFactory;

    protected $table = 'komentar';

    protected function casts(): array
    {
        return [
            'minta_penjelasan' => 'boolean',
            'status_permintaan' => StatusPermintaan::class,
            'ditutup_pada' => 'datetime',
        ];
    }

    /** Open Minta Penjelasan roots: what the Fase 8 dashboards count. */
    public function scopePermintaanTerbuka(Builder $q): void
    {
        $q->whereNull('parent_id')->where('minta_penjelasan', true)->where('status_permintaan', StatusPermintaan::Terbuka);
    }

    public function spt()
    {
        return $this->belongsTo(Spt::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function balasan()
    {
        return $this->hasMany(self::class, 'parent_id')->oldest('id');
    }

    public function penulis()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function diinputOleh()
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }

    public function ditutupOleh()
    {
        return $this->belongsTo(User::class, 'ditutup_oleh');
    }
}
