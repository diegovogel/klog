<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

it('allows demoting a deactivated admin even when only one active admin remains', function () {
    $deactivatedAdmin = User::factory()->admin()->deactivated()->create();

    $this->patch(route('settings.users.role.update', $deactivatedAdmin), [
        'role' => UserRole::MEMBER->value,
    ])->assertRedirect(route('settings'));

    expect($deactivatedAdmin->fresh()->role)->toBe(UserRole::MEMBER);
});

it('still blocks demoting the last active admin', function () {
    $other = User::factory()->admin()->create();

    // Demote the additional active admin so $this->admin is now the only active admin.
    $this->patch(route('settings.users.role.update', $other), [
        'role' => UserRole::MEMBER->value,
    ])->assertRedirect(route('settings'));
    expect($other->fresh()->role)->toBe(UserRole::MEMBER);

    // Demoting the last active admin must still be rejected.
    $this->patch(route('settings.users.role.update', $this->admin), [
        'role' => UserRole::MEMBER->value,
    ])->assertSessionHasErrors('role');
});
