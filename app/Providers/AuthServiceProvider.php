<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     * @see https://www.ritolab.com/entry/56
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('system', function ($user) {
            return $user->role->value == 1;
        });

        Gate::define('admin', function ($user) {
            return $user->role->value <= 5;
        });

        Gate::define('user', function ($user) {
            return $user->role->value <= 10;
        });
    }
}
