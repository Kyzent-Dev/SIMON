<?php

namespace App\Models;

use Database\Factories\PkptPerubahanRiwayatFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Audit trail of a Perubahan PKPT; `oleh` null = sistem. */
#[Fillable(['perubahan_id', 'kejadian', 'oleh', 'catatan'])]
class PkptPerubahanRiwayat extends Model
{
    /** @use HasFactory<PkptPerubahanRiwayatFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'pkpt_perubahan_riwayat';

    public function perubahan()
    {
        return $this->belongsTo(PkptPerubahan::class, 'perubahan_id');
    }

    public function pengguna()
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
