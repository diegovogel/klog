<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        // Only advertise the one-click demo login (and pre-fill the credentials)
        // once the demo account actually exists, so a demo instance that hasn't
        // been seeded yet shows a normal, working login form instead of a dead
        // button. The demo banner itself stays tied to the demo flag.
        $demoLoginAvailable = config('klog.is_demo')
            && User::active()->where('email', config('klog.demo_email'))->exists();

        return view('auth.login', ['demoLoginAvailable' => $demoLoginAvailable]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        // Re-stamp because session()->regenerate() preserves data but the
        // listener fired before the regenerate. +1s for the same reason as
        // the listener — inclusive comparison in EnsureUserActive.
        $request->session()->put('auth.created_at', now()->getTimestamp() + 1);

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')
            ->withCookie(cookie()->forget('two_factor_remember'));
    }
}
