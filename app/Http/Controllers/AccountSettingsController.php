<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\LogOutOtherDevicesRequest;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AccountSettingsController extends Controller
{
    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
        ])->save();

        return redirect()->route('settings')->with('success', 'Account updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();
        $now = now();

        // Rotate credentials and bump the per-user session epoch so any other
        // active session (or stolen remember-me cookie) gets kicked out on
        // its next request. Wipe remembered 2FA devices so the next 2FA-gated
        // access also re-challenges.
        $user->forceFill([
            'password' => $request->validated('password'),
            'session_invalidated_at' => $now,
            'remember_token' => Str::random(60),
        ])->save();
        $user->rememberedDevices()->delete();

        // Stamp the current session +1s so the inclusive epoch comparison
        // in EnsureUserActive doesn't kick the actor out as well.
        $request->session()->put('auth.created_at', $now->copy()->addSecond()->getTimestamp());

        return redirect()->route('settings')
            ->with('success', 'Password updated.')
            ->withCookie(cookie()->forget('two_factor_remember'));
    }

    public function logOutOtherDevices(LogOutOtherDevicesRequest $request): RedirectResponse
    {
        $user = $request->user();

        // Rotates the user's remember_token and (with AuthenticateSession) password hash.
        Auth::logoutOtherDevices($request->validated('password'));

        // Backend-agnostic invalidation: bump the per-user session epoch and
        // re-stamp the current session so EnsureUserActive logs out anything older.
        // Stamp the current session +1s after the epoch so the inclusive comparison
        // in the middleware (which treats equal seconds as stale) doesn't kick us out.
        $now = now();
        $user->update(['session_invalidated_at' => $now]);
        $request->session()->put('auth.created_at', $now->copy()->addSecond()->getTimestamp());

        $currentToken = $request->cookie('two_factor_remember');
        $query = $user->rememberedDevices();

        if (is_string($currentToken) && $currentToken !== '') {
            $query->where('token_hash', '!=', hash('sha256', $currentToken));
        }

        $query->delete();

        return redirect()->route('settings')
            ->with('success', 'Other devices have been logged out.');
    }
}
