<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Context;
use LogicException;
use Spatie\Activitylog\Models\Activity;

/** Activity log row (PRD §7.12): adds "Diinput oleh" and the request ip; rows are never updated or deleted. */
class Aktivitas extends Activity
{
    public const ANAK_SPT = [SptPersonel::class, SptDokumen::class, SptKendala::class, Komentar::class];

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            // Console without a test (scheduler, queue worker) has no real client: Request::create() would report 127.0.0.1.
            $a->ip ??= app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip();
            $a->diinput_oleh ??= Context::get('diinput_oleh') ?? $a->penginputSubjek();
            $a->spt_id ??= $a->sptSubjek();
        });
        static::updating(fn () => throw new LogicException('Log aktivitas tidak dapat diubah atau dihapus.'));
        static::deleting(fn () => throw new LogicException('Log aktivitas tidak dapat diubah atau dihapus.'));
    }

    /** The subject's own diinput_oleh, only when this very write set it (an old value belongs to an earlier penginput). */
    private function penginputSubjek(): ?int
    {
        $subjek = $this->relationLoaded('subject') ? $this->getRelation('subject') : null;
        if (! $subjek || ! array_key_exists('diinput_oleh', $subjek->getAttributes())) {
            return null;
        }

        return ($this->event === 'created' || $subjek->wasChanged('diinput_oleh')) ? $subjek->diinput_oleh : null;
    }

    public function diinputOleh(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'diinput_oleh');
    }

    /** The SPT this row belongs to: the subject itself, or a child's spt_id (as held, else as logged: a deleted child keeps it in `old`). */
    private function sptSubjek(): ?int
    {
        if ($this->subject_type === (new Spt)->getMorphClass()) {
            return $this->subject_id;
        }
        if (! in_array($this->subject_type, array_map(fn ($k) => (new $k)->getMorphClass(), self::ANAK_SPT), true)) {
            return null;
        }
        $id = $this->relationLoaded('subject') ? $this->getRelation('subject')?->spt_id : null;

        return $id ?? $this->properties['old']['spt_id'] ?? $this->properties['attributes']['spt_id'] ?? null;
    }

    /** Rows of the SPT and of its personel, dokumen, kendala and komentar (indexed `spt_id`, set when the row is written). */
    public function scopeUntukSpt(Builder $q, int $sptId): Builder
    {
        return $q->where('spt_id', $sptId);
    }

    public static function catatImpor(User $user, string $jenis, string $berkas, int $jumlah): void
    {
        activity('impor')->causedBy($user)->event('impor')
            ->withProperties(['jenis' => $jenis, 'berkas' => $berkas, 'jumlah' => $jumlah])->log('impor');
    }

    public static function imporTerakhir(): ?self
    {
        return static::where('log_name', 'impor')->latest('id')->first();
    }
}
