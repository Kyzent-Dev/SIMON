<?php

namespace App\Console\Commands;

use Spatie\Activitylog\CleanActivitylogCommand;

/** Replaces the package's `activitylog:clean` (bound in AppServiceProvider): activity_log rows are never deleted (PRD §7.12). */
class TolakBersihkanLog extends CleanActivitylogCommand
{
    public function handle(): int
    {
        $this->error('Log aktivitas tidak boleh dihapus (PRD §7.12).');

        return self::FAILURE;
    }
}
