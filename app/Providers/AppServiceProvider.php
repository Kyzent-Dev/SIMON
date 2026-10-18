<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Settings::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // One Admin-only gate for every Fase 2 master-data screen (DECISIONS.md).
        Gate::define('manage-master-data', fn (User $user) => $user->role === Role::Admin);
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers());
    }
}
