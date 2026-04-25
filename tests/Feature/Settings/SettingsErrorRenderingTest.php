<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserInvite;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'password']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

it('shows admin user-management errors on the settings page', function () {
    $invitee = User::factory()->create();
    UserInvite::factory()->for($invitee)->accepted()->create();

    $this->post(route('settings.users.resend-invite', $invitee));

    $this->get(route('settings'))
        ->assertSuccessful()
        ->assertSee('User has already accepted their invite');
});

it('shows the last-admin-demotion error', function () {
    $this->patch(route('settings.users.role.update', $this->admin), [
        'role' => UserRole::MEMBER->value,
    ]);

    $this->get(route('settings'))
        ->assertSuccessful()
        ->assertSee('You cannot demote yourself');
});
