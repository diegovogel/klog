<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Settings\InviteUserRequest;
use App\Http\Requests\Settings\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\UserInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function __construct(private UserInviteService $invites) {}

    public function invite(InviteUserRequest $request): RedirectResponse
    {
        $this->invites->invite([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'role' => UserRole::from($request->validated('role')),
        ]);

        return redirect()->route('settings')->with('success', 'Invitation sent.');
    }

    public function resendInvite(Request $request, User $user): RedirectResponse
    {
        try {
            $this->invites->resend($user);
        } catch (\RuntimeException $e) {
            return redirect()->route('settings')->withErrors(['invite' => $e->getMessage()]);
        }

        return redirect()->route('settings')->with('success', 'Invitation resent.');
    }

    public function updateRole(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $newRole = UserRole::from($request->validated('role'));

        if ($user->id === $request->user()->id && $newRole !== UserRole::ADMIN) {
            return redirect()->route('settings')->withErrors(['role' => 'You cannot demote yourself.']);
        }

        // Only enforce the last-admin guard when demoting an *active* admin —
        // a deactivated admin doesn't count toward the active-admin pool, so
        // demoting them never reduces it.
        if ($user->isAdmin() && $user->isActive() && $newRole !== UserRole::ADMIN && $this->adminCount() <= 1) {
            return redirect()->route('settings')->withErrors(['role' => 'At least one admin must remain.']);
        }

        $user->forceFill(['role' => $newRole])->save();

        return redirect()->route('settings')->with('success', 'Role updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('settings')->withErrors(['deactivate' => 'You cannot deactivate yourself.']);
        }

        if ($user->isAdmin() && $this->adminCount() <= 1) {
            return redirect()->route('settings')->withErrors(['deactivate' => 'At least one active admin must remain.']);
        }

        $user->deactivate();

        return redirect()->route('settings')->with('success', 'User deactivated.');
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $user->reactivate();

        return redirect()->route('settings')->with('success', 'User reactivated.');
    }

    private function adminCount(): int
    {
        return User::query()
            ->where('role', UserRole::ADMIN)
            ->active()
            ->count();
    }
}
