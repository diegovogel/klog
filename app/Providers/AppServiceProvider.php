<?php

namespace App\Providers;

use App\Listeners\StampAuthCreatedAt;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        Gate::define('manage-app-settings', fn (User $user) => $user->isAdmin());

        Event::listen(Login::class, StampAuthCreatedAt::class);
    }
}
