<?php

// Child process for PerubahanPkptKonkurensiTest: boots the app against the throwaway database given by DB_CONNECTION/DB_DATABASE (env)
// and approves one Perubahan PKPT. Args: perubahan id, pimpinan user id, barrier dir (see Tests\Support\KonkurensiProses).
// Prints {"ok": true, "nomor_urut": N} or, for a validation error, {"ok": false, "pesan": "..."}.

use App\Actions\Pkpt\Perubahan\PutuskanPerubahan;
use App\Models\PkptPerubahan;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = $argv[3];
touch($dir.'/siap-'.getmypid());
for ($batas = microtime(true) + 60; ! file_exists($dir.'/mulai'); usleep(5000)) {
    if (microtime(true) > $batas) {
        fwrite(STDERR, 'Barrier timeout');
        exit(1);
    }
}

try {
    $p = app(PutuskanPerubahan::class)->handle(User::findOrFail((int) $argv[2]), PkptPerubahan::findOrFail((int) $argv[1]), true, null);
    echo json_encode(['ok' => true, 'nomor_urut' => $p->nomor_urut]);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'pesan' => collect($e->errors())->flatten()->implode(' ')]);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(1);
}
