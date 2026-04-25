<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

/**
 * Records the timestamp at which the current session became authenticated.
 * The EnsureUserActive middleware compares this against the per-user
 * session_invalidated_at epoch to decide if a session has been revoked.
 *
 * Listening to the Login event covers both fresh credential logins and
 * Laravel's recaller-cookie ("remember me") flow — the SessionGuard fires
 * Login from both paths, so we don't need to stamp inside individual
 * controllers.
 */
class StampAuthCreatedAt
{
    public function handle(Login $event): void
    {
        if ($event->guard !== 'web') {
            return;
        }

        if (! request()->hasSession()) {
            return;
        }

        request()->session()->put('auth.created_at', now()->getTimestamp());
    }
}
