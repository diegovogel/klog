<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

describe('user:create', function () {
    it('should create a member by default', function () {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->expectsChoice('Role', 'member', ['admin', 'member'])
            ->expectsOutput('User jane@example.com created.')
            ->assertSuccessful();

        $user = User::where('email', 'jane@example.com')->first();

        expect($user)->not->toBeNull()
            ->and($user->name)->toBe('Jane Doe')
            ->and(Hash::check('secret123', $user->password))->toBeTrue()
            ->and($user->role)->toBe(UserRole::MEMBER);
    });

    it('should create an admin when selected', function () {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm password', 'secret123')
            ->expectsChoice('Role', 'admin', ['admin', 'member'])
            ->expectsOutput('User jane@example.com created.')
            ->assertSuccessful();

        $user = User::where('email', 'jane@example.com')->first();

        expect($user->role)->toBe(UserRole::ADMIN)
            ->and($user->isAdmin())->toBeTrue();
    });

    it('should fail when passwords do not match', function () {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('Password', 'secret123')
            ->expectsQuestion('Confirm password', 'different')
            ->expectsOutput('Passwords do not match.')
            ->assertFailed();

        expect(User::where('email', 'jane@example.com')->exists())->toBeFalse();
    });

    it('should fail when email is already taken', function () {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('user:create')
            ->expectsQuestion('Name', 'Jane Doe')
            ->expectsQuestion('Email', 'taken@example.com')
            ->assertFailed();

        expect(User::where('email', 'taken@example.com')->count())->toBe(1);
    });
});

describe('user:reset-password', function () {
    it('should reset the password for an existing user', function () {
        $user = User::factory()->create(['email' => 'jane@example.com']);

        $this->artisan('user:reset-password')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('New password', 'newpassword')
            ->expectsQuestion('Confirm new password', 'newpassword')
            ->expectsOutput('Password updated for jane@example.com.')
            ->assertSuccessful();

        $user->refresh();
        expect(Hash::check('newpassword', $user->password))->toBeTrue();
    });

    it('should fail when passwords do not match', function () {
        User::factory()->create(['email' => 'jane@example.com', 'password' => 'original']);

        $this->artisan('user:reset-password')
            ->expectsQuestion('Email', 'jane@example.com')
            ->expectsQuestion('New password', 'newpassword')
            ->expectsQuestion('Confirm new password', 'different')
            ->expectsOutput('Passwords do not match.')
            ->assertFailed();

        $user = User::where('email', 'jane@example.com')->first();
        expect(Hash::check('original', $user->password))->toBeTrue();
    });

    it('should fail when user does not exist', function () {
        $this->artisan('user:reset-password')
            ->expectsQuestion('Email', 'nobody@example.com')
            ->expectsOutput('No user found with email nobody@example.com.')
            ->assertFailed();
    });
});

describe('user:change-role', function () {
    it('should promote a member to admin', function () {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'role' => UserRole::MEMBER,
        ]);

        $this->artisan('user:change-role', ['email' => 'jane@example.com', 'role' => 'admin'])
            ->expectsOutput('Role for jane@example.com changed to admin.')
            ->assertSuccessful();

        expect($user->fresh()->role)->toBe(UserRole::ADMIN);
    });

    it('should demote an admin to member', function () {
        User::factory()->create(['role' => UserRole::ADMIN]);
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'role' => UserRole::ADMIN,
        ]);

        $this->artisan('user:change-role', ['email' => 'jane@example.com', 'role' => 'member'])
            ->expectsOutput('Role for jane@example.com changed to member.')
            ->assertSuccessful();

        expect($user->fresh()->role)->toBe(UserRole::MEMBER);
    });

    it('should fail when user does not exist', function () {
        $this->artisan('user:change-role', ['email' => 'nobody@example.com', 'role' => 'admin'])
            ->expectsOutput('No user found with email nobody@example.com.')
            ->assertFailed();
    });

    it('should fail when role is invalid', function () {
        User::factory()->create(['email' => 'jane@example.com', 'role' => UserRole::MEMBER]);

        $this->artisan('user:change-role', ['email' => 'jane@example.com', 'role' => 'wizard'])
            ->expectsOutputToContain("Invalid role 'wizard'.")
            ->assertFailed();
    });

    it('should refuse to demote the last active admin', function () {
        $user = User::factory()->create([
            'email' => 'jane@example.com',
            'role' => UserRole::ADMIN,
        ]);
        User::factory()->create([
            'role' => UserRole::ADMIN,
            'deactivated_at' => now(),
        ]);

        $this->artisan('user:change-role', ['email' => 'jane@example.com', 'role' => 'member'])
            ->expectsOutput('At least one admin must remain.')
            ->assertFailed();

        expect($user->fresh()->role)->toBe(UserRole::ADMIN);
    });

    it('should be a no-op when the user already has the requested role', function () {
        User::factory()->create(['email' => 'jane@example.com', 'role' => UserRole::ADMIN]);

        $this->artisan('user:change-role', ['email' => 'jane@example.com', 'role' => 'admin'])
            ->expectsOutput('jane@example.com already has role admin.')
            ->assertSuccessful();
    });
});
