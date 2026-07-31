<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\User;

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
        Gate::policy(\App\Models\Patient::class, \App\Policies\PatientPolicy::class);
        Gate::define('manage-tenant', function (User $user) {
            return $user->user_type === 'admin';
        });

        Gate::define('write-clinical', function (User $user) {
            return in_array($user->user_type, ['professional']);
        });
    }
}
