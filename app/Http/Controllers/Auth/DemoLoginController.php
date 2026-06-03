<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoLoginController extends Controller
{
    /**
     * Log the visitor straight in as the shared demo account.
     *
     * Only reachable when the app runs in demo mode. Mirrors the session
     * handling in LoginController: Auth::login() fires the Login event so
     * StampAuthCreatedAt stamps auth.created_at, and we re-stamp +1s after
     * regenerate() for the inclusive comparison in EnsureUserActive.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('klog.is_demo'), 404);

        $user = User::active()->where('email', config('klog.demo_email'))->first();

        abort_if($user === null, 503, 'Demo account is not available.');

        Auth::guard('web')->login($user);

        $request->session()->regenerate();
        $request->session()->put('auth.created_at', now()->getTimestamp() + 1);

        return redirect()->intended('/');
    }
}
