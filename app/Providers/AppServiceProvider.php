<?php

namespace App\Providers;

use App\Console\Commands\TolakBersihkanLog;
use App\Enums\Role;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
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
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
    }
}
