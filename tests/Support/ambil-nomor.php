<?php

// Child process for PenomoranKonkurensiTest: boots the app against the SQLite file given by DB_DATABASE (env),
// takes one number inside a transaction and prints it as JSON. Args: irban id, decision date (Y-m-d).

use App\Models\Irban;
use App\Services\Penomoran;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $irban = Irban::findOrFail((int) $argv[1]);
    $hasil = DB::transaction(fn () => app(Penomoran::class)->ambil($irban, CarbonImmutable::parse($argv[2])));
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(1);
}

echo json_encode(['nomor_urut' => $hasil['nomor_urut'], 'nomor_lengkap' => $hasil['nomor_lengkap']]);
