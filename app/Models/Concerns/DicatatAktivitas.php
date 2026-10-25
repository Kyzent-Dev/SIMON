<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Logs created/updated/deleted of an important model (Fase 10 E1): only changed columns, never hidden ones or secrets. */
trait DicatatAktivitas
{
    use LogsActivity {
        logChanges as protected logChangesSpatie;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()
            ->logExcept([...$this->getHidden(), 'created_at', 'updated_at', 'password', 'remember_token'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /** Spatie serializes dates as UTC ISO (`serializeDate`); log them as stored, in Jakarta time: `Y-m-d` or `Y-m-d H:i:s`. */
    public static function logChanges(Model $model): array
    {
        $changes = static::logChangesSpatie($model);
        foreach ($changes as $kolom => $nilai) {
            if (is_string($nilai) && $model->isDateAttribute($kolom)) {
                $tanggalSaja = in_array(Str::before($model->getCasts()[$kolom] ?? '', ':'), ['date', 'immutable_date'], true);
                $changes[$kolom] = Carbon::parse($nilai)->timezone(config('app.timezone'))->format($tanggalSaja ? 'Y-m-d' : 'Y-m-d H:i:s');
            }
        }

        return $changes;
    }
}
