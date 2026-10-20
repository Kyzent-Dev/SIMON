<?php

namespace App\Http\Controllers;

use App\Models\Ekspor;
use Illuminate\Support\Facades\Storage;

class EksporController extends Controller
{
    public function __invoke(Ekspor $ekspor)
    {
        abort_unless($ekspor->user_id === auth()->id() && $ekspor->status === 'selesai' && Storage::disk('local')->exists((string) $ekspor->path), 404);

        return Storage::disk('local')->download($ekspor->path, $ekspor->nama_file);
    }
}
