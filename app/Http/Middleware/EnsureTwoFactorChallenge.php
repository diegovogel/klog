<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorChallenge
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('two_factor_confirmed')) {
            return $next($request);
        }

        $rememberToken = $request->cookie('two_factor_remember');

        if ($rememberToken && $this->twoFactorService->verifyRememberToken($user, $rememberToken)) {
            $request->session()->put('two_factor_confirmed', true);

            return $next($request);
        }

        return redirect()->route('two-factor.challenge');
    }
}
