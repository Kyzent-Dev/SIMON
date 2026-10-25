<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** Logs created/updated/deleted of an important model (Fase 10 E1): only changed columns, never hidden ones or secrets. */
trait DicatatAktivitas
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll()
            ->logExcept([...$this->getHidden(), 'created_at', 'updated_at', 'password', 'remember_token'])
            ->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
