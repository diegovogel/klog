<?php

use App\Models\User;

it('invalidates other browser sessions while keeping the current one alive', function () {
    $user = User::factory()->create(['password' => 'password']);

    // Simulate a "current" session that's already authenticated.
    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(5)->getTimestamp(),
    ]);

    $this->post(route('settings.log-out-other-devices'), ['password' => 'password'])
        ->assertRedirect(route('settings'));

    // The user-level session epoch should now be set.
    expect($user->fresh()->session_invalidated_at)->not->toBeNull();

    // The "current" session got its auth.created_at re-stamped, so it stays alive.
    $this->get('/')->assertSuccessful();

    // Simulate any *other* session by clearing the request's session and reapplying
    // an old auth.created_at — that must be kicked out by EnsureUserActive.
    $this->flushSession();
    $this->actingAs($user)->withSession([
        'two_factor_confirmed' => true,
        'auth.created_at' => now()->subMinutes(10)->getTimestamp(),
    ]);

    $this->get('/')->assertRedirect(route('login'));
    $this->assertGuest();
});
