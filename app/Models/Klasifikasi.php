<?php

namespace App\Models;

use App\Enums\SyaratSelesai;
use App\Models\Concerns\DicatatAktivitas;
use App\Models\Concerns\MenyegarkanCachePublik;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// Admin edits the rules only; the five rows are fixed (no create/delete UI).
#[Fillable(['wajib_item_pkpt', 'syarat_selesai', 'tampil_publik', 'warna'])]
class Klasifikasi extends Model
{
    use DicatatAktivitas, MenyegarkanCachePublik;

    protected $table = 'klasifikasi';

    protected function casts(): array
    {
        return ['wajib_item_pkpt' => 'boolean', 'tampil_publik' => 'boolean', 'syarat_selesai' => SyaratSelesai::class];
    }
}
