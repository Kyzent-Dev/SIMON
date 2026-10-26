<?php

namespace App\Providers;

use App\Console\Commands\TolakBersihkanLog;
use App\Enums\Role;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\CleanActivitylogCommand;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
        // The package registers activitylog:clean by class name; Artisan resolves it through the container, so this swap wins.
        $this->app->bind(CleanActivitylogCommand::class, TolakBersihkanLog::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One Admin-only gate for every Fase 2 master-data screen (DECISIONS.md).
        Gate::define('manage-master-data', fn (User $user) => $user->role === Role::Admin);
        Gate::define('lihat-log', fn (User $user) => in_array($user->role, [Role::Admin, Role::Pimpinan], true));
        // Read from config here (not in bootstrap/app.php, where config is not loaded yet).
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
        // Public pages: 120 requests per minute per IP (applied by `throttle:publik` on the three public GET routes).
        RateLimiter::for('publik', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
    }
}
