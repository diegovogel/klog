<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ChangeUserRoleCommand extends Command
{
    protected $signature = 'user:change-role {email : The email address of the user} {role : The new role (admin or member)}';

    protected $description = "Change a user's role";

    public function handle(): int
    {
        $email = $this->argument('email');
        $roleValue = $this->argument('role');

        $newRole = UserRole::tryFrom($roleValue);

        if ($newRole === null) {
            $this->error("Invalid role '{$roleValue}'. Valid roles: ".implode(', ', UserRole::values()).'.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if ($user->role === $newRole) {
            $this->info("{$email} already has role {$newRole->value}.");

            return self::SUCCESS;
        }

        try {
            DB::transaction(function () use ($user, $newRole) {
                $locked = User::query()->lockForUpdate()->findOrFail($user->id);

                if ($locked->isAdmin() && $locked->isActive() && $newRole !== UserRole::ADMIN
                    && $this->activeAdminCountForUpdate() <= 1) {
                    throw new \RuntimeException('At least one admin must remain.');
                }

                $locked->forceFill(['role' => $newRole])->save();
            });
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Role for {$email} changed to {$newRole->value}.");

        return self::SUCCESS;
    }

    private function activeAdminCountForUpdate(): int
    {
        return User::query()
            ->where('role', UserRole::ADMIN)
            ->active()
            ->lockForUpdate()
            ->count();
    }
}
