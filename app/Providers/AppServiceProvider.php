<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

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
        // Define gate for API documentation access
        Gate::define('viewApiDocs', function ($user = null) {
            // Allow access in local environment
            if (app()->environment('local')) {
                return true;
            }
            
            // In production, only allow authenticated admin users
            return $user && $user->role === 'admin';
        });
    }
}
