<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\LogOutOtherDevicesRequest;
use App\Http\Requests\Settings\UpdateAccountRequest;
use App\Http\Requests\Settings\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

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
        $request->user()->forceFill([
            'password' => $request->validated('password'),
        ])->save();

        return redirect()->route('settings')->with('success', 'Password updated.');
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
