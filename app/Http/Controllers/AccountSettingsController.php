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
        Auth::logoutOtherDevices($request->validated('password'));

        $currentToken = $request->cookie('two_factor_remember');
        $query = $request->user()->rememberedDevices();

        if (is_string($currentToken) && $currentToken !== '') {
            $query->where('token_hash', '!=', hash('sha256', $currentToken));
        }

        $query->delete();

        return redirect()->route('settings')
            ->with('success', 'Other devices have been logged out.');
    }
}
