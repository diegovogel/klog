<?php

use App\Enums\UserRole;
use App\Models\User;

describe('User role', function () {
    it('defaults new users to member', function () {
        $user = User::factory()->create();

        expect($user->role)->toBe(UserRole::MEMBER)
            ->and($user->isAdmin())->toBeFalse();
    });

    it('creates admins via the factory state', function () {
        $admin = User::factory()->admin()->create();

        expect($admin->role)->toBe(UserRole::ADMIN)
            ->and($admin->isAdmin())->toBeTrue();
    });
});

describe('manage-app-settings gate', function () {
    it('allows admins', function () {
        $admin = User::factory()->admin()->create();

        expect($admin->can('manage-app-settings'))->toBeTrue();
    });

    it('denies members', function () {
        $member = User::factory()->create();

        expect($member->can('manage-app-settings'))->toBeFalse();
    });
});
