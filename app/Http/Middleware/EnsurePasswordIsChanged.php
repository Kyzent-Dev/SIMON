<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->wajib_ganti_password && ! $request->routeIs('password.force.*', 'logout')) {
            return redirect()->route('password.force.edit');
        }

        return $next($request);
    }
}
