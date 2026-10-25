<?php

namespace App\Models;

use App\Enums\AksiUsulan;
use App\Enums\StatusUsulan;
use App\Models\Concerns\DicatatAktivitas;
use Database\Factories\PkptUsulanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** An Irban's proposal for a change to a Disahkan PKPT; Admin takes it into a Perubahan or closes it. */
#[Fillable(['pkpt_id', 'irban_id', 'pkpt_item_id', 'aksi_usulan', 'tanggal_mulai', 'tanggal_selesai', 'jenis_penugasan_id', 'alasan', 'diajukan_oleh', 'diinput_oleh'])]
class PkptUsulan extends Model
{
    /** @use HasFactory<PkptUsulanFactory> */
    use DicatatAktivitas, HasFactory;

    protected $table = 'pkpt_usulan';

    protected $attributes = ['status' => 'terbuka'];

    protected function casts(): array
    {
        return [
            'aksi_usulan' => AksiUsulan::class,
            'status' => StatusUsulan::class,
            'tanggal_mulai' => 'immutable_date',
            'tanggal_selesai' => 'immutable_date',
        ];
    }

    public function pkpt()
    {
        return $this->belongsTo(Pkpt::class);
    }

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    public function item()
    {
        return $this->belongsTo(PkptItem::class, 'pkpt_item_id');
    }

    public function jenisPenugasan()
    {
        return $this->belongsTo(JenisPenugasan::class);
    }

    public function perubahan()
    {
        return $this->belongsTo(PkptPerubahan::class, 'perubahan_id');
    }

    public function diajukanOleh()
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
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
