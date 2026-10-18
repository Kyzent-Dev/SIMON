<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AttemptLogin;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        // Survives a reload after a throttled attempt: old input means we were redirected back.
        $old = old('username');
        $key = $old ? AttemptLogin::key($old, (string) request()->ip()) : null;
        $lockout = $key && RateLimiter::tooManyAttempts($key, AttemptLogin::MAX_ATTEMPTS) ? RateLimiter::availableIn($key) : 0;

        return view('auth.login', ['lockout' => max($lockout, (int) session('lockout_seconds', 0))]);
    }

    public function store(Request $request, AttemptLogin $attempt): RedirectResponse
    {
        $data = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);

        $attempt($data['username'], $data['password'], $request->boolean('remember'), (string) $request->ip());
        $request->session()->regenerate();

        $own = route($request->user()->role->dashboardRoute());
        $intended = $request->session()->get('url.intended');
        $dashboards = array_map(fn ($r) => route($r), ['admin.dashboard', 'pimpinan.dashboard', 'irban.dashboard', 'bagian-umum.dashboard']);
        if (in_array($intended, $dashboards, true) && $intended !== $own) {
            $request->session()->forget('url.intended');
        }

        return redirect()->intended($own);
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
