<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ChangeForcedPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForcedPasswordController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->wajib_ganti_password) {
            return redirect()->route('dashboard');
        }

        return view('auth.force-password');
    }

    public function update(Request $request, ChangeForcedPassword $change): RedirectResponse
    {
        $change($request->user(), $request->only('password', 'password_confirmation'));

        return redirect()->route($request->user()->role->dashboardRoute());
    }
}
