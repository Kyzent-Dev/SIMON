<?php

namespace App\Models;

use Database\Factories\JenisPenugasanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nama', 'warna', 'warna_teks'])]
class JenisPenugasan extends Model
{
    /** @use HasFactory<JenisPenugasanFactory> */
    use HasFactory;

    protected $table = 'jenis_penugasan';

    /** shortcut: nothing references jenis_penugasan until PKPT (Fase 3); check the PKPT item table there, then. */
    public function isReferenced(): bool
    {
        return false;
    }
}
