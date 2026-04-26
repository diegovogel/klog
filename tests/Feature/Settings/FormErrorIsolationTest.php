<?php

use App\Enums\UserRole;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['password' => 'password', 'name' => 'Real Name', 'email' => 'real@example.com']);
    $this->actingAs($this->admin)->withSession(['two_factor_confirmed' => true]);
});

it('does not leak the invite form errors into the account form', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    // Submit the invite form with a duplicate email — that fails validation.
    $this->post(route('settings.users.invite'), [
        'name' => 'Spy',
        'email' => 'taken@example.com',
        'role' => UserRole::MEMBER->value,
    ])->assertRedirect();

    $response = $this->get(route('settings'))->assertSuccessful();
    $body = (string) $response->getContent();

    // Invite section should show "has already been taken"
    expect($body)->toContain('has already been taken');

    // Account form's name input must still hold the user's real name (not "Spy").
    expect($body)->toContain('value="Real Name"');
    expect($body)->toContain('value="real@example.com"');
});

it('does not leak the account form errors into the invite form', function () {
    // Submit account form with bad data (email change without password).
    $this->patch(route('settings.account.update'), [
        'name' => 'Pretender',
        'email' => 'newish@example.com',
    ])->assertRedirect();

    $response = $this->get(route('settings'))->assertSuccessful();
    $body = (string) $response->getContent();

    // Account form should repopulate with submitted values.
    expect($body)->toContain('value="Pretender"');

    // Invite form's name field must remain empty (no leakage of "Pretender").
    expect(preg_match('/<input id="invite-name"[^>]*value="Pretender"/', $body))->toBe(0);
});
