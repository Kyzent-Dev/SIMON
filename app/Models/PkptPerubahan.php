<?php

namespace App\Models;

use App\Enums\JenisPerubahan;
use App\Enums\StatusPerubahan;
use Database\Factories\PkptPerubahanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** A Perubahan PKPT package: many operations on a Disahkan PKPT, applied only after Pimpinan approval. */
#[Fillable(['pkpt_id', 'jenis', 'alasan', 'nomor_sk', 'tanggal_sk'])]
class PkptPerubahan extends Model
{
    /** @use HasFactory<PkptPerubahanFactory> */
    use HasFactory;

    protected $table = 'pkpt_perubahan';

    protected $attributes = ['status' => 'draf', 'sumber' => 'admin'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisPerubahan::class,
            'status' => StatusPerubahan::class,
            'diajukan_pada' => 'datetime',
            'diputuskan_pada' => 'datetime',
            'tanggal_sk' => 'immutable_date',
            'sk_lengkap_pada' => 'datetime',
            'pengingat_sk_dimatikan_pada' => 'datetime',
            'pengingat_terakhir_pada' => 'datetime',
        ];
    }

    public function pkpt()
    {
        return $this->belongsTo(Pkpt::class);
    }

    public function operasi()
    {
        return $this->hasMany(PkptPerubahanItem::class, 'perubahan_id')->orderBy('urutan')->orderBy('id');
    }

    public function riwayat()
    {
        return $this->hasMany(PkptPerubahanRiwayat::class, 'perubahan_id');
    }

    public function usulan()
    {
        return $this->hasMany(PkptUsulan::class, 'perubahan_id');
    }

    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function pemutus()
    {
        return $this->belongsTo(User::class, 'diputuskan_oleh');
    }

    /** SK counts as complete when number, date and PDF are all filled. */
    public function skLengkap(): bool
    {
        return filled($this->nomor_sk) && $this->tanggal_sk !== null && filled($this->berkas_sk);
    }
}
