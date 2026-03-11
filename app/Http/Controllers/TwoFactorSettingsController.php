<?php

namespace App\Http\Controllers;

use App\Enums\TwoFactorMethod;
use App\Services\AuthenticatorService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TwoFactorSettingsController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
        private AuthenticatorService $authenticatorService,
    ) {}

    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'method' => ['required', Rule::in(TwoFactorMethod::values())],
            'password' => ['required', 'current_password'],
        ]);

        $method = TwoFactorMethod::from($request->input('method'));

        if ($method === TwoFactorMethod::AUTHENTICATOR) {
            if (! $this->authenticatorService->isAvailable()) {
                return back()->withErrors(['method' => 'Authenticator app is not available.']);
            }

            return redirect()->route('two-factor.authenticator.setup');
        }

        $recoveryCodes = $this->twoFactorService->enable($request->user(), $method);

        return redirect()->route('settings')
            ->with('recovery_codes', $recoveryCodes)
            ->with('success', 'Two-factor authentication has been enabled.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $this->twoFactorService->disable($request->user());

        return redirect()->route('settings')
            ->with('success', 'Two-factor authentication has been disabled.')
            ->withCookie(cookie()->forget('two_factor_remember'));
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $codes = TwoFactorService::generateRecoveryCodes();

        $request->user()->update([
            'two_factor_recovery_codes' => TwoFactorService::hashRecoveryCodes($codes),
        ]);

        return redirect()->route('settings')
            ->with('recovery_codes', $codes)
            ->with('success', 'Recovery codes have been regenerated.');
    }

    public function showAuthenticatorSetup(Request $request): View
    {
        if (! $this->authenticatorService->isAvailable()) {
            abort(404);
        }

        $secret = $request->session()->get('two_factor_setup_secret')
            ?? $this->authenticatorService->generateSecret();

        $request->session()->put('two_factor_setup_secret', $secret);

        $qrCodeUri = $this->authenticatorService->qrCodeUri($request->user(), $secret);
        $qrCodeSvg = $this->authenticatorService->generateQrCodeSvg($qrCodeUri);

        return view('settings.two-factor-authenticator-setup', [
            'secret' => $secret,
            'qrCodeSvg' => $qrCodeSvg,
        ]);
    }

    public function confirmAuthenticator(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $secret = $request->session()->get('two_factor_setup_secret');

        if (! $secret || ! $this->authenticatorService->verify($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'The provided code is invalid. Please try again.']);
        }

        $user = $request->user();
        $user->two_factor_secret = $secret;
        $user->save();

        $recoveryCodes = $this->twoFactorService->enable($user, TwoFactorMethod::AUTHENTICATOR);

        $request->session()->forget('two_factor_setup_secret');

        return redirect()->route('settings')
            ->with('recovery_codes', $recoveryCodes)
            ->with('success', 'Authenticator app has been configured.');
    }
}
