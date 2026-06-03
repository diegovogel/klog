<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: env('HEALTH_CHECK_ENABLED', false) ? '/up' : null,
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo('/login');
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // In demo mode the per-IP write throttles must see the real client IP.
        // Trust ONLY the specific fronting proxy CIDRs given in DEMO_TRUSTED_PROXIES
        // (comma-separated). Left unset, no forwarded headers are trusted, so
        // request()->ip() is the direct REMOTE_ADDR — correct when the demo points
        // straight at the origin (how it's deployed). Never trust '*': that lets a
        // direct client spoof X-Forwarded-For and evade the throttles. Scoped to
        // demo so production proxy handling is untouched (env() matches the
        // HEALTH_CHECK_ENABLED pattern below).
        if (env('IS_DEMO', false)) {
            $demoProxies = (string) env('DEMO_TRUSTED_PROXIES', '');

            if ($demoProxies !== '' && $demoProxies !== '*') {
                $middleware->trustProxies(at: explode(',', $demoProxies));
            }
        }
        $middleware->alias([
            'two-factor' => \App\Http\Middleware\EnsureTwoFactorChallenge::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'user-active' => \App\Http\Middleware\EnsureUserActive::class,
            'block-in-demo' => \App\Http\Middleware\BlockInDemo::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
