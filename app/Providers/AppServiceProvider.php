<?php

namespace App\Providers;

use App\Listeners\StampAuthCreatedAt;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureDemoRateLimiters();
    }

    /**
     * Throttle write-heavy endpoints on the public demo to bound abuse.
     *
     * Both limiters are keyed by IP (not user id) because every demo visitor
     * shares one account — keying by user would lump all visitors into a single
     * bucket. Outside demo mode they resolve to Limit::none(), so production
     * keeps its normal unthrottled single-user behaviour. Chunk uploads get a
     * higher ceiling because one legitimate file is split across many
     * sequential chunk requests.
     */
    private function configureDemoRateLimiters(): void
    {
        RateLimiter::for('demo-writes', fn (Request $request) => config('klog.is_demo')
            ? Limit::perMinute(30)->by($request->ip())
            : Limit::none());

        RateLimiter::for('demo-chunks', fn (Request $request) => config('klog.is_demo')
            ? Limit::perMinute(120)->by($request->ip())
            : Limit::none());
    }
}
