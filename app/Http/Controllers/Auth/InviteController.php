<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInviteRequest;
use App\Models\UserInvite;
use App\Services\UserInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class InviteController extends Controller
{
    public function __construct(private UserInviteService $invites) {}

    public function show(string $token): View
    {
        $invite = $this->findUsable($token);

        return view('auth.accept-invite', [
            'invite' => $invite,
            'token' => $token,
        ]);
    }

    public function accept(AcceptInviteRequest $request, string $token): RedirectResponse
    {
        $invite = $this->findUsable($token);

        $user = $this->invites->accept(
            $invite,
            $request->validated('name'),
            $request->validated('password'),
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')->with('success', 'Welcome to '.config('app.name').'.');
    }

    private function findUsable(string $token): UserInvite
    {
        $invite = UserInvite::findByToken($token);

        if (! $invite || ! $invite->isUsable()) {
            abort(404);
        }

        if ($invite->user?->isDeactivated()) {
            abort(404);
        }

        return $invite;
    }
}
