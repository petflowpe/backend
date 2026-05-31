<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use App\Console\Commands\CreateDirectoryStructure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Laravel puede intentar resolver el limiter como "<UserClass>::api" cuando hay usuario autenticado.
        RateLimiter::for(User::class . '::api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        \App\Models\Pet::observe(\App\Observers\PetObserver::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                CreateDirectoryStructure::class,
            ]);
        }
    }
}
