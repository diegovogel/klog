<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isDeactivated() || $this->sessionPredatesInvalidation($user, $request)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withCookie(cookie()->forget('two_factor_remember'))
                ->withErrors(['email' => __('auth.failed')]);
        }

        return $next($request);
    }

    private function sessionPredatesInvalidation(\App\Models\User $user, Request $request): bool
    {
        if ($user->session_invalidated_at === null) {
            return false;
        }

        $createdAt = $request->session()->get('auth.created_at');

        return $createdAt === null
            || $createdAt < $user->session_invalidated_at->getTimestamp();
    }
}
