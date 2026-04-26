<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Settings\InviteUserRequest;
use App\Http\Requests\Settings\UpdateUserRoleRequest;
use App\Models\User;
use App\Services\UserInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        try {
            DB::transaction(function () use ($user, $newRole) {
                // Lock the target row + the active-admin set so two concurrent
                // role changes can't both observe count > 1 and leave zero
                // active admins.
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);

                if ($locked->isAdmin() && $locked->isActive() && $newRole !== UserRole::ADMIN
                    && $this->adminCountForUpdate() <= 1) {
                    throw new \RuntimeException('At least one admin must remain.');
                }

                $locked->forceFill(['role' => $newRole])->save();
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('settings')->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()->route('settings')->with('success', 'Role updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return redirect()->route('settings')->withErrors(['deactivate' => 'You cannot deactivate yourself.']);
        }

        try {
            DB::transaction(function () use ($user) {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);

                if ($locked->isAdmin() && $locked->isActive() && $this->adminCountForUpdate() <= 1) {
                    throw new \RuntimeException('At least one active admin must remain.');
                }

                $locked->deactivate();
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('settings')->withErrors(['deactivate' => $e->getMessage()]);
        }

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

    /**
     * Like adminCount() but locks the matching rows so concurrent
     * role/deactivate transactions serialize on the same admin pool.
     */
    private function adminCountForUpdate(): int
    {
        return User::query()
            ->where('role', UserRole::ADMIN)
            ->active()
            ->lockForUpdate()
            ->count();
    }
}
