<?php

namespace App\Console\Commands;

use App\Models\Ekspor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class EksporBersihkan extends Command
{
    protected $signature = 'ekspor:bersihkan';

    protected $description = 'Hapus file ekspor dan barisnya yang berumur lebih dari 7 hari.';

    public function handle(): int
    {
        $jumlah = 0;
        // lazyById: deleting inside offset chunks would skip rows.
        Ekspor::where('created_at', '<', now()->subDays(7))->lazyById()->each(function (Ekspor $e) use (&$jumlah) {
            if ($e->path) {
                Storage::disk('local')->delete($e->path);
            }
            $e->delete();
            $jumlah++;
        });
        $this->info("{$jumlah} ekspor dihapus.");

        return self::SUCCESS;
    }
}
