<?php

namespace App\Models;

use App\Enums\StatusPengajuan;
use App\Enums\StatusSpt;
use App\Enums\SumberSpt;
use App\Services\Settings;
use App\Support\Tanggal;
use Database\Factories\SptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Status, number and authorship columns are set by the actions with forceFill, never mass-assigned from input.
#[Fillable(['irban_id', 'klasifikasi_id', 'pkpt_item_id', 'jenis_penugasan_id', 'ppj_pegawai_id', 'periode_diperiksa', 'uraian_untuk',
    'tanggal_mulai', 'tanggal_selesai', 'catatan_bagian_umum', 'diinput_oleh', 'penandatangan_pegawai_id', 'status_penandatangan'])]
class Spt extends Model
{
    /** @use HasFactory<SptFactory> */
    use HasFactory;

    protected $table = 'spt';

    protected function casts(): array
    {
        return [
            'status_pengajuan' => StatusPengajuan::class,
            'sumber' => SumberSpt::class,
            'tanggal_mulai' => 'immutable_date',
            'tanggal_selesai' => 'immutable_date',
            'tanggal_spt' => 'immutable_date',
            'tanggal_lhp' => 'immutable_date',
            'diajukan_pada' => 'immutable_datetime',
        ];
    }

    /** Computed from status_pengajuan and today in Asia/Jakarta (PRD §9.2); no cron. */
    public function status(): StatusSpt
    {
        return match ($this->status_pengajuan ?? StatusPengajuan::Draf) {
            StatusPengajuan::Draf => StatusSpt::Draf,
            StatusPengajuan::MenungguNomor => StatusSpt::MenungguNomor,
            StatusPengajuan::PerluRevisi => StatusSpt::PerluRevisi,
            StatusPengajuan::Ditolak => StatusSpt::Ditolak,
            StatusPengajuan::Dibatalkan => StatusSpt::Dibatalkan,
            StatusPengajuan::Diterima => $this->statusBerjalan(),
        };
    }

    private function statusBerjalan(): StatusSpt
    {
        // Fase 5: return StatusSpt::Selesai here when the klasifikasi's syarat selesai are met (before the date checks).
        $hariIni = now('Asia/Jakarta')->toDateString();
        if ($this->tanggal_mulai === null || $hariIni < $this->tanggal_mulai->toDateString()) {
            return StatusSpt::SiapDilaksanakan;
        }

        return $hariIni <= $this->tanggal_selesai?->toDateString() ? StatusSpt::Pelaksanaan : StatusSpt::PenyusunanLaporan;
    }

    /** SQL twin of status() for list filters; keep both in step. */
    public function scopeBerstatus(Builder $q, StatusSpt $status): void
    {
        $hariIni = now('Asia/Jakarta')->toDateString();
        $diterima = fn () => $q->where('status_pengajuan', StatusPengajuan::Diterima);
        match ($status) {
            StatusSpt::SiapDilaksanakan => $diterima()->where(fn ($w) => $w->whereNull('tanggal_mulai')->orWhereDate('tanggal_mulai', '>', $hariIni)),
            StatusSpt::Pelaksanaan => $diterima()->whereDate('tanggal_mulai', '<=', $hariIni)->where(fn ($w) => $w->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $hariIni)),
            StatusSpt::PenyusunanLaporan => $diterima()->whereDate('tanggal_mulai', '<=', $hariIni)->whereDate('tanggal_selesai', '<', $hariIni),
            StatusSpt::Selesai => $q->whereRaw('1 = 0'), // Fase 5
            default => $q->where('status_pengajuan', StatusPengajuan::from($status->value)),
        };
    }

    /** Inclusive days, kalender or kerja per `penugasan.hitungan_hari`; null while dates are incomplete. */
    public function durasi(): ?int
    {
        if ($this->tanggal_mulai === null || $this->tanggal_selesai === null) {
            return null;
        }

        return Tanggal::durasi($this->tanggal_mulai, $this->tanggal_selesai, app(Settings::class)->get('penugasan.hitungan_hari') === 'kerja');
    }

    public function irban()
    {
        return $this->belongsTo(Irban::class);
    }

    public function klasifikasi()
    {
        return $this->belongsTo(Klasifikasi::class);
    }

    public function pkptItem()
    {
        return $this->belongsTo(PkptItem::class);
    }

    public function jenisPenugasan()
    {
        return $this->belongsTo(JenisPenugasan::class);
    }

    public function ppj()
    {
        return $this->belongsTo(Pegawai::class, 'ppj_pegawai_id');
    }

    public function penandatangan()
    {
        return $this->belongsTo(Pegawai::class, 'penandatangan_pegawai_id');
    }

    public function nomorCounter()
    {
        return $this->belongsTo(NomorCounter::class);
    }

    public function diinputOleh()
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }

    public function dibuatOleh()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function objek()
    {
        return $this->belongsToMany(Objek::class, 'spt_objek')->withTimestamps();
    }

    public function personel()
    {
        return $this->hasMany(SptPersonel::class)->orderBy('urutan');
    }

    public function dokumen()
    {
        return $this->hasMany(SptDokumen::class);
    }

    public function riwayat()
    {
        return $this->hasMany(SptRiwayat::class);
    }
}
