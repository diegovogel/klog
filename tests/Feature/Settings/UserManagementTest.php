<?php

use App\Enums\UserRole;
use App\Mail\UserInvited;
use App\Models\User;
use App\Models\UserInvite;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

describe('invite', function () {
    it('creates a user, an invite, and sends the mail', function () {
        $this->post(route('settings.users.invite'), [
            'name' => 'Newbie',
            'email' => 'newbie@example.com',
            'role' => UserRole::MEMBER->value,
        ])->assertRedirect(route('settings'));

        $user = User::where('email', 'newbie@example.com')->first();
        expect($user)->not->toBeNull()
            ->and($user->role)->toBe(UserRole::MEMBER)
            ->and($user->invite)->not->toBeNull()
            ->and($user->invite->isUsable())->toBeTrue();

        Mail::assertSent(UserInvited::class, fn (UserInvited $mail) => $mail->hasTo('newbie@example.com'));
    });

    it('rejects duplicate email', function () {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->post(route('settings.users.invite'), [
            'name' => 'Dup',
            'email' => 'dup@example.com',
            'role' => UserRole::MEMBER->value,
        ])->assertSessionHasErrors('email', null, 'invite');
    });

    it('forbids non-admins', function () {
        $member = User::factory()->create();
        $this->actingAs($member)->withSession(['two_factor_confirmed' => true]);

        $this->post(route('settings.users.invite'), [
            'name' => 'X',
            'email' => 'x@example.com',
            'role' => UserRole::MEMBER->value,
        ])->assertForbidden();
    });
});

describe('resend invite', function () {
    it('replaces the existing token and re-sends', function () {
        $invitee = User::factory()->create();
        $oldInvite = UserInvite::factory()->for($invitee)->create();

        $this->post(route('settings.users.resend-invite', $invitee))
            ->assertRedirect(route('settings'));

        expect(UserInvite::where('token', $oldInvite->token)->exists())->toBeFalse();
        expect($invitee->fresh()->invite)->not->toBeNull();

        Mail::assertSent(UserInvited::class);
    });

    it('blocks resending after acceptance', function () {
        $invitee = User::factory()->create();
        UserInvite::factory()->for($invitee)->accepted()->create();

        $this->post(route('settings.users.resend-invite', $invitee))
            ->assertSessionHasErrors('invite');
    });
});

describe('role updates', function () {
    it('blocks self-demotion', function () {
        $this->patch(route('settings.users.role.update', $this->admin), [
            'role' => UserRole::MEMBER->value,
        ])->assertSessionHasErrors('role');

        expect($this->admin->fresh()->isAdmin())->toBeTrue();
    });

    it('blocks demoting the last admin', function () {
        $member = User::factory()->create();
        $other = User::factory()->admin()->create();

        // Two admins exist, so demoting one is allowed.
        $this->patch(route('settings.users.role.update', $other), [
            'role' => UserRole::MEMBER->value,
        ])->assertRedirect(route('settings'));
        expect($other->fresh()->role)->toBe(UserRole::MEMBER);

        // Only $this->admin remains as admin; demoting them must fail.
        $this->patch(route('settings.users.role.update', $this->admin), [
            'role' => UserRole::MEMBER->value,
        ])->assertSessionHasErrors('role');
    });

    it('promotes a member to admin', function () {
        $member = User::factory()->create();

        $this->patch(route('settings.users.role.update', $member), [
            'role' => UserRole::ADMIN->value,
        ])->assertRedirect(route('settings'));

        expect($member->fresh()->isAdmin())->toBeTrue();
    });
});

describe('deactivate / reactivate', function () {
    it('blocks self-deactivation', function () {
        $this->post(route('settings.users.deactivate', $this->admin))
            ->assertSessionHasErrors('deactivate');
    });

    it('blocks deactivating the last admin', function () {
        $other = User::factory()->admin()->create();

        $this->post(route('settings.users.deactivate', $other))
            ->assertRedirect(route('settings'));
        expect($other->fresh()->isDeactivated())->toBeTrue();

        // Now $this->admin is the only active admin; another user (member or else) is fine,
        // but an attempt to deactivate the last admin should fail.
        $admin2 = User::factory()->admin()->create();
        $this->post(route('settings.users.deactivate', $admin2))->assertRedirect(route('settings'));

        $this->post(route('settings.users.deactivate', $this->admin))->assertSessionHasErrors('deactivate');
    });

    it('clears remembered devices when deactivating', function () {
        $member = User::factory()->create();
        $member->rememberedDevices()->create([
            'token_hash' => hash('sha256', 'a'),
            'expires_at' => now()->addDays(10),
        ]);

        $this->post(route('settings.users.deactivate', $member))
            ->assertRedirect(route('settings'));

        expect($member->fresh()->isDeactivated())->toBeTrue()
            ->and($member->fresh()->rememberedDevices)->toHaveCount(0);
    });

    it('reactivates a user', function () {
        $member = User::factory()->deactivated()->create();

        $this->post(route('settings.users.reactivate', $member))
            ->assertRedirect(route('settings'));

        expect($member->fresh()->isActive())->toBeTrue();
    });
});
