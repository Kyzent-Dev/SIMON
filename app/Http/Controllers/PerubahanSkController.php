<?php

namespace App\Http\Controllers;

use App\Models\PkptPerubahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PerubahanSkController extends Controller
{
    /** Streams the SK PDF from the private disk: inline, or attachment with ?unduh=1. Admin, Pimpinan and Irban of a touched irban. */
    public function __invoke(Request $request, PkptPerubahan $perubahan): Response
    {
        abort_if($perubahan->berkas_sk === null, 404);
        Gate::authorize('unduhSk', $perubahan);
        abort_unless(Storage::disk('local')->exists($perubahan->berkas_sk), 404);
        $nama = 'sk-perubahan-pkpt-'.$perubahan->pkpt->tahun.($perubahan->nomor_urut ? '-ke-'.$perubahan->nomor_urut : '').'.pdf';

        return Storage::disk('local')->response($perubahan->berkas_sk, $nama, ['Content-Type' => 'application/pdf'],
            $request->boolean('unduh') ? 'attachment' : 'inline');
    }
}
