<?php

namespace App\Http\Controllers\Auth;

use App\Enums\TwoFactorMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Mail\TwoFactorCodeMail;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled() || $request->session()->get('two_factor_confirmed')) {
            return redirect('/');
        }

        if ($user->usesTwoFactorMethod(TwoFactorMethod::EMAIL)) {
            $this->sendEmailCode($user);
        }

        return view('auth.two-factor-challenge', [
            'method' => $user->two_factor_method,
        ]);
    }

    public function verify(TwoFactorChallengeRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($this->twoFactorService->isRateLimited($user)) {
            return back()->withErrors([
                'code' => 'Too many attempts. Please wait before trying again.',
            ]);
        }

        $code = $request->validated('code');
        $isRecovery = $request->boolean('recovery');

        $verified = $isRecovery
            ? $this->twoFactorService->verifyRecoveryCode($user, $code)
            : $this->twoFactorService->verify($user, $code);

        if (! $verified) {
            $this->twoFactorService->hitRateLimit($user);

            return back()->withErrors(['code' => 'The provided code is invalid.']);
        }

        $this->twoFactorService->clearRateLimit($user);
        $request->session()->put('two_factor_confirmed', true);

        $response = redirect()->intended('/');

        if ($request->boolean('remember')) {
            $token = $this->twoFactorService->generateRememberToken($user);
            $days = config('klog.two_factor.remember_days', 30);

            $response->withCookie(cookie('two_factor_remember', $token, $days * 24 * 60));
        }

        return $response;
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled() && $user->usesTwoFactorMethod(TwoFactorMethod::EMAIL)) {
            $this->sendEmailCode($user);
        }

        return back()->with('status', 'A new code has been sent to your email.');
    }

    private function sendEmailCode($user): void
    {
        $code = $this->twoFactorService->issueEmailCode($user);
        $ttl = config('klog.two_factor.email_code_ttl', 10);

        Mail::to($user)->send(new TwoFactorCodeMail($code, $ttl));
    }
}
