<?php

namespace App\Http\Controllers;

use App\Models\Spt;
use App\Models\SptDokumen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class SptDokumenController extends Controller
{
    /** Streams from the private disk: inline for "Lihat", attachment with ?unduh=1. */
    public function __invoke(Request $request, Spt $spt, SptDokumen $dokumen): Response
    {
        Gate::authorize('view', $spt);
        abort_unless($dokumen->spt_id === $spt->id && Storage::disk('local')->exists($dokumen->path), 404);

        return Storage::disk('local')->response($dokumen->path, $dokumen->nama_asli, ['Content-Type' => 'application/pdf'],
            $request->boolean('unduh') ? 'attachment' : 'inline');
    }
}
