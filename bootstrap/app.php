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
        $middleware->alias([
            'two-factor' => \App\Http\Middleware\EnsureTwoFactorChallenge::class,
            'admin' => \App\Http\Middleware\RequireAdmin::class,
            'user-active' => \App\Http\Middleware\EnsureUserActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
